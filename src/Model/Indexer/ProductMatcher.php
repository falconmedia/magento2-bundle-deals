<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Indexer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Rule\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Finds the visible, enabled products that match the conditions of a rule or slot.
 *
 * An empty condition tree matches nothing, so a rule never applies to the whole catalog by accident.
 */
class ProductMatcher
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductFactory $productFactory,
        private readonly Iterator $iterator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility
    ) {
    }

    /**
     * Returns the sort data of the products that match the conditions of a bundle deal or slot.
     *
     * @param int[] $includeIds products to add regardless of the conditions (manual links)
     * @return array<int, array{name: string, price: float, created_at: string}> product id => sort data
     */
    public function match(AbstractModel $model, array $includeIds = []): array
    {
        $conditions = $model->getConditions();
        $hasConditions = (bool) $conditions->getConditions();
        if (!$hasConditions && !$includeIds) {
            return [];
        }

        $defaultStore = $this->storeManager->getDefaultStoreView();
        $storeId = (int) $defaultStore?->getId();
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'price'], 'left')
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->setVisibility($this->visibility->getVisibleInSiteIds());
        // Configurables have no own price; the guest min_price of the price index is their sort price.
        $collection->getSelect()->joinLeft(
            ['bundle_price_index' => $collection->getTable('catalog_product_index_price')],
            $collection->getConnection()->quoteInto(
                'bundle_price_index.entity_id = e.entity_id AND bundle_price_index.customer_group_id = 0'
                . ' AND bundle_price_index.website_id = ?',
                (int) $defaultStore?->getWebsiteId()
            ),
            ['bundle_min_price' => 'bundle_price_index.min_price']
        );

        $model->setData('collected_attributes', []);
        if ($hasConditions) {
            $conditions->collectValidatedAttributes($collection);
        } else {
            $collection->addIdFilter($includeIds);
        }

        $include = array_fill_keys(array_map('intval', $includeIds), true);
        $result = [];
        $prototype = $this->productFactory->create();
        $this->iterator->walk(
            $collection->getSelect(),
            [function (array $args) use ($conditions, $hasConditions, $include, $storeId, &$result): void {
                /**
                 * @var Product $product
                 */
                $product = clone $args['product'];
                $product->setData($args['row']);
                $product->setStoreId($storeId);
                $productId = (int) $product->getId();
                if (isset($include[$productId]) || ($hasConditions && $conditions->validate($product))) {
                    $result[$productId] = [
                        'name' => (string) $product->getData('name'),
                        'price' => (float) ($product->getData('bundle_min_price') ?? $product->getData('price')),
                        'created_at' => (string) $product->getData('created_at'),
                    ];
                }
            }],
            ['product' => $prototype]
        );

        return $result;
    }
}
