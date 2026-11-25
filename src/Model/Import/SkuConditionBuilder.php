<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Import;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\CatalogRule\Model\Rule\Condition\Product as ProductCondition;

/**
 * Builds the slot condition tree, in loadPost() format, that matches a list of SKUs.
 */
class SkuConditionBuilder
{
    /**
     * Builds a condition tree that matches the given SKUs.
     *
     * @param string[] $skus
     * @return array<string,array<string,string>>
     */
    public function build(array $skus): array
    {
        return [
            '1' => ['type' => Combine::class, 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
            '1--1' => [
                'type' => ProductCondition::class,
                'attribute' => 'sku',
                'operator' => '()',
                'value' => implode(', ', $skus),
            ],
        ];
    }
}
