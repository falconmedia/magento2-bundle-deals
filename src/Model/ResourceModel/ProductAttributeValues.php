<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads raw product attribute values, option labels, colour swatches and colour variant links in bulk.
 */
class ProductAttributeValues
{
    private const SWATCH_TABLE = 'eav_attribute_option_swatch';
    private const SWATCH_TYPE_VISUAL = 1;
    private const COLOR_VARIANT_LINK_CODE = 'color_variant';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var array<string,array<int,string>>
     */
    private $labelCache = [];

    public function __construct(ResourceConnection $resource, EavConfig $eavConfig, MetadataPool $metadataPool)
    {
        $this->resource = $resource;
        $this->eavConfig = $eavConfig;
        $this->metadataPool = $metadataPool;
    }

    public function attributeExists(string $attributeCode): bool
    {
        return $this->getAttribute($attributeCode) !== null;
    }

    /**
     * Raw values of a product attribute, the store view value over the default value.
     *
     * @param int[] $productIds
     * @return array<int,string> keyed by product id; products without a value are left out
     */
    public function getValues(string $attributeCode, array $productIds, int $storeId = 0): array
    {
        $attribute = $this->getAttribute($attributeCode);
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($attribute === null || !$productIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        if ($attribute->isStatic()) {
            $select = $connection->select()
                ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id', $attributeCode])
                ->where('entity_id IN (?)', $productIds);

            return array_filter(
                array_map('strval', $connection->fetchPairs($select)),
                static function (string $value): bool {
                    return $value !== '';
                }
            );
        }

        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $select = $connection->select()
            ->from(['e' => $this->resource->getTableName('catalog_product_entity')], ['entity_id'])
            ->join(
                ['v' => $attribute->getBackendTable()],
                'v.' . $linkField . ' = e.' . $linkField,
                ['store_id', 'value']
            )
            ->where('e.entity_id IN (?)', $productIds)
            ->where('v.attribute_id = ?', (int) $attribute->getId())
            ->where('v.store_id IN (?)', array_unique([0, $storeId]))
            ->order('v.store_id ASC');

        $values = [];
        foreach ($connection->fetchAll($select) as $row) {
            if ($row['value'] === null || (string) $row['value'] === '') {
                continue;
            }
            $values[(int) $row['entity_id']] = (string) $row['value'];
        }

        return $values;
    }

    /**
     * Option ids of a select or multiselect attribute per product.
     *
     * @param int[] $productIds
     * @return array<int,int[]> keyed by product id
     */
    public function getOptionIds(string $attributeCode, array $productIds, int $storeId = 0): array
    {
        $result = [];
        foreach ($this->getValues($attributeCode, $productIds, $storeId) as $productId => $value) {
            $optionIds = array_values(array_filter(array_map('intval', explode(',', $value))));
            if ($optionIds) {
                $result[$productId] = $optionIds;
            }
        }

        return $result;
    }

    /**
     * Option labels of an attribute, the store view label over the admin label.
     *
     * @return array<int,string> keyed by option id
     */
    public function getOptionLabels(string $attributeCode, int $storeId = 0): array
    {
        $key = $attributeCode . '|' . $storeId;
        if (isset($this->labelCache[$key])) {
            return $this->labelCache[$key];
        }
        $attribute = $this->getAttribute($attributeCode);
        if ($attribute === null) {
            return $this->labelCache[$key] = [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->resource->getTableName('eav_attribute_option')], ['option_id'])
            ->join(
                ['v' => $this->resource->getTableName('eav_attribute_option_value')],
                'v.option_id = o.option_id',
                ['value']
            )
            ->where('o.attribute_id = ?', (int) $attribute->getId())
            ->where('v.store_id IN (?)', array_unique([0, $storeId]))
            ->order('v.store_id ASC');

        $labels = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (trim((string) $row['value']) !== '') {
                $labels[(int) $row['option_id']] = trim((string) $row['value']);
            }
        }

        return $this->labelCache[$key] = $labels;
    }

    /**
     * Visual colour swatches (hex values) of attribute options.
     *
     * @param int[] $optionIds
     * @return array<int,string> keyed by option id
     */
    public function getSwatchHexes(array $optionIds, int $storeId = 0): array
    {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::SWATCH_TABLE);
        if (!$optionIds || !$connection->isTableExists($table)) {
            return [];
        }
        $select = $connection->select()
            ->from($table, ['option_id', 'value'])
            ->where('option_id IN (?)', $optionIds)
            ->where('type = ?', self::SWATCH_TYPE_VISUAL)
            ->where('store_id IN (?)', array_unique([0, $storeId]))
            ->order('store_id ASC');

        $hexes = [];
        foreach ($connection->fetchAll($select) as $row) {
            $value = trim((string) $row['value']);
            if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
                $hexes[(int) $row['option_id']] = strtolower($value);
            }
        }

        return $hexes;
    }

    /**
     * Colour variant links (product link type "color_variant") between the given products and others.
     *
     * @param int[] $productIds
     * @return array<int,int[]> product id => linked product ids
     */
    public function getColorVariantLinks(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $connection = $this->resource->getConnection();
        if (!$productIds) {
            return [];
        }
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName('catalog_product_link')], ['product_id', 'linked_product_id'])
            ->join(
                ['t' => $this->resource->getTableName('catalog_product_link_type')],
                't.link_type_id = l.link_type_id',
                []
            )
            ->where('t.code = ?', self::COLOR_VARIANT_LINK_CODE)
            ->where('l.product_id IN (?)', $productIds);

        $links = [];
        foreach ($connection->fetchAll($select) as $row) {
            $links[(int) $row['product_id']][] = (int) $row['linked_product_id'];
        }

        return $links;
    }

    /**
     * Returns a product attribute by code, or null when it does not exist.
     */
    private function getAttribute(string $attributeCode): ?AbstractAttribute
    {
        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        } catch (LocalizedException $e) {
            return null;
        }

        return $attribute && $attribute->getId() ? $attribute : null;
    }
}
