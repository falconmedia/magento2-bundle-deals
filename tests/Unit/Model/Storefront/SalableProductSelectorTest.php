<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Storefront;

use FalconMedia\BundleDeals\Model\Storefront\ProductDataBuilder;
use FalconMedia\BundleDeals\Model\Storefront\SalableProductSelector;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class SalableProductSelectorTest extends TestCase
{
    /**
     * Product data builder stub that only returns the given salable ids, in request order.
     *
     * @param int[] $salableIds
     */
    private function selector(array $salableIds, ?int &$calls = null): SalableProductSelector
    {
        $calls = 0;
        $builder = $this->createMock(ProductDataBuilder::class);
        $builder->method('loadProducts')->willReturnCallback(
            function (array $ids) use ($salableIds, &$calls): array {
                $calls++;
                $loaded = [];
                foreach ($ids as $id) {
                    if (in_array($id, $salableIds, true)) {
                        $loaded[$id] = $this->createMock(Product::class);
                    }
                }

                return $loaded;
            }
        );

        return new SalableProductSelector($builder);
    }

    public function testUnsalableProductsBeforeTheLimitAreSkipped(): void
    {
        $productIds = range(1, 12);
        $selected = $this->selector([9, 10, 11, 12])->select($productIds, 1, 8);

        self::assertSame([9, 10, 11, 12], array_keys($selected));
    }

    public function testSalableProductsBeyondTheFirstBatchAreFound(): void
    {
        $productIds = range(1, 60);
        $selected = $this->selector([45, 50], $calls)->select($productIds, 1, 8);

        self::assertSame([45, 50], array_keys($selected));
        self::assertSame(3, $calls);
    }

    public function testSelectionStopsAtTheLimit(): void
    {
        $productIds = range(1, 100);
        $selected = $this->selector(range(1, 100), $calls)->select($productIds, 1, 3);

        self::assertSame([1, 2, 3], array_keys($selected));
        self::assertSame(1, $calls);
    }

    public function testNoSalableProductsGiveAnEmptySelection(): void
    {
        self::assertSame([], $this->selector([])->select(range(1, 8), 1, 8));
    }
}
