<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Storefront;

use Magento\Catalog\Model\Product;

/**
 * Picks the first salable products of a slot, loading the candidates in batches so unsalable ones are skipped
 * before the limit applies.
 */
class SalableProductSelector
{
    private const MIN_BATCH_SIZE = 20;

    public function __construct(
        private readonly ProductDataBuilder $productDataBuilder
    ) {
    }

    /**
     * The first salable, visible products in the given order.
     *
     * @param int[] $productIds candidates in display order
     * @return Product[] keyed by product id
     */
    public function select(array $productIds, int $storeId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $selected = [];
        foreach (array_chunk($productIds, max($limit * 2, self::MIN_BATCH_SIZE)) as $batch) {
            foreach ($this->productDataBuilder->loadProducts($batch, $storeId) as $productId => $product) {
                $selected[$productId] = $product;
                if (count($selected) >= $limit) {
                    return $selected;
                }
            }
        }

        return $selected;
    }
}
