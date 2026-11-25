<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Delivery;

use FalconMedia\BundleDeals\Api\DeliveryTimeProviderInterface;

/**
 * Default delivery time provider: no delivery times, so the block shows none.
 */
class NullDeliveryTimeProvider implements DeliveryTimeProviderInterface
{
    /**
     * @inheritdoc
     */
    public function getForProductIds(array $productIds, int $storeId): array
    {
        return [];
    }
}
