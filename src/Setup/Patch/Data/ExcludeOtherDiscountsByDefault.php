<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Setup\Patch\Data;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Keeps coupon and cart price rule discounts off the items of all existing bundle deals.
 */
class ExcludeOtherDiscountsByDefault implements DataPatchInterface
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(Rule::TABLE),
            ['exclude_other_discounts' => 1],
            ['exclude_other_discounts = ?' => 0]
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
