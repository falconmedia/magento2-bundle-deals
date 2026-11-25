<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Import\Amasty;

use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Reads Amasty packs and the products they refer to straight from the database tables.
 */
class PackReader
{
    public const PACK_TABLE = 'amasty_mostviewed_pack';
    public const PRODUCT_TABLE = 'amasty_mostviewed_pack_product';
    public const STORE_TABLE = 'amasty_mostviewed_pack_store';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ProductAttributeValues
     */
    private $attributeValues;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    public function __construct(
        ResourceConnection $resource,
        ProductAttributeValues $attributeValues,
        MetadataPool $metadataPool
    ) {
        $this->resource = $resource;
        $this->attributeValues = $attributeValues;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Returns the Amasty tables that do not exist.
     *
     * @return string[]
     */
    public function getMissingTables(): array
    {
        $connection = $this->resource->getConnection();
        $missing = [];
        foreach ([self::PACK_TABLE, self::PRODUCT_TABLE, self::STORE_TABLE] as $table) {
            if (!$connection->isTableExists($this->resource->getTableName($table))) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * Returns pack rows with their main product ids ("parent_ids") and store ids ("store_ids").
     *
     * @param int[] $packIds empty for all packs
     * @return array<int,array<string,mixed>> keyed by pack id
     */
    public function getPacks(array $packIds = []): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::PACK_TABLE))
            ->order('pack_id ASC');
        if ($packIds) {
            $select->where('pack_id IN (?)', array_map('intval', $packIds));
        }
        $packs = [];
        foreach ($connection->fetchAll($select) as $row) {
            $row['parent_ids'] = [];
            $row['store_ids'] = [];
            $packs[(int) $row['pack_id']] = $row;
        }
        if (!$packs) {
            return [];
        }

        $select = $connection->select()
            ->from($this->resource->getTableName(self::PRODUCT_TABLE), ['pack_id', 'product_id'])
            ->where('pack_id IN (?)', array_keys($packs))
            ->order('entity_id ASC');
        foreach ($connection->fetchAll($select) as $row) {
            $packs[(int) $row['pack_id']]['parent_ids'][] = (int) $row['product_id'];
        }

        $select = $connection->select()
            ->from($this->resource->getTableName(self::STORE_TABLE), ['pack_id', 'store_id'])
            ->where('pack_id IN (?)', array_keys($packs));
        foreach ($connection->fetchAll($select) as $row) {
            $packs[(int) $row['pack_id']]['store_ids'][] = (int) $row['store_id'];
        }
        foreach ($packs as $packId => $pack) {
            if (!$pack['store_ids']) {
                $packs[$packId]['store_ids'] = [(int) ($pack['store_id'] ?? 0)];
            }
        }

        return $packs;
    }

    /**
     * Returns existing products by id with sku, admin name and configurable parent id.
     *
     * The parent id is set for simples that are not visible on their own; those parents are included.
     *
     * @param int[] $productIds
     * @return array<int,array<string,mixed>>
     */
    public function getProducts(array $productIds): array
    {
        $products = $this->loadProducts($productIds);
        $hidden = array_keys(array_filter(
            $this->attributeValues->getValues('visibility', array_keys($products), 0),
            static function (string $visibility): bool {
                return (int) $visibility === Visibility::VISIBILITY_NOT_VISIBLE;
            }
        ));
        $parents = $this->getConfigurableParents($hidden);
        $products += $this->loadProducts(array_diff(array_values($parents), array_keys($products)));
        foreach ($parents as $childId => $parentId) {
            if (isset($products[$childId], $products[$parentId])) {
                $products[$childId]['parent_id'] = $parentId;
            }
        }

        return $products;
    }

    /**
     * Loads products by id with sku and admin name.
     *
     * @param int[] $productIds
     * @return array<int,array<string,mixed>>
     */
    private function loadProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id', 'sku'])
            ->where('entity_id IN (?)', $productIds);
        $names = $this->attributeValues->getValues('name', $productIds, 0);

        $products = [];
        foreach ($connection->fetchPairs($select) as $productId => $sku) {
            $products[(int) $productId] = [
                'sku' => (string) $sku,
                'name' => $names[(int) $productId] ?? (string) $sku,
                'parent_id' => null,
            ];
        }

        return $products;
    }

    /**
     * Configurable parent entity id per child product id; the lowest parent id wins for shared children.
     *
     * @param int[] $childIds
     * @return array<int,int>
     */
    private function getConfigurableParents(array $childIds): array
    {
        if (!$childIds) {
            return [];
        }
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['l' => $this->resource->getTableName('catalog_product_super_link')], ['product_id'])
            ->join(
                ['e' => $this->resource->getTableName('catalog_product_entity')],
                'e.' . $linkField . ' = l.parent_id',
                ['parent_entity_id' => 'MIN(e.entity_id)']
            )
            ->where('l.product_id IN (?)', array_map('intval', $childIds))
            ->group('l.product_id');

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Collects every main and item product id referenced by the packs.
     *
     * @param array<int,array<string,mixed>> $packs
     * @return int[]
     */
    public function collectProductIds(array $packs): array
    {
        $ids = [];
        foreach ($packs as $pack) {
            foreach ((array) $pack['parent_ids'] as $id) {
                $ids[] = (int) $id;
            }
            foreach (explode(',', (string) ($pack['product_ids'] ?? '')) as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
