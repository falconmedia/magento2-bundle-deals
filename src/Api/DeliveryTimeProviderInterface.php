<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Api;

/**
 * Supplies the expected delivery time per product for the bundle deal block.
 *
 * The module ships a provider without delivery times; a stock module can replace it through a DI preference.
 */
interface DeliveryTimeProviderInterface
{
    /**
     * Delivery times keyed by product id; products without a known delivery time are left out.
     *
     * @param int[] $productIds
     * @return array<int, array{label: string, minDays: int|null, maxDays: int|null, direct: bool}>
     */
    public function getForProductIds(array $productIds, int $storeId): array;
}
