<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Plugin\SalesRule;

use FalconMedia\BundleDeals\Model\Rule\Condition\AbstractBundleCondition;
use FalconMedia\BundleDeals\Model\Rule\Condition\ItemInSet;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine;

/**
 * Offers "Item belongs to complete bundle deal set" in cart item conditions and rule actions.
 */
class AddItemCondition
{
    /**
     * Adds the bundle deal item condition to the options.
     *
     * @param array<int,mixed> $result
     * @return array<int, mixed>
     */
    public function afterGetNewChildSelectOptions(Combine $subject, array $result): array
    {
        $result[] = [
            'label' => __('Bundle Deals'),
            'value' => [
                [
                    'value' => ItemInSet::class . '|' . AbstractBundleCondition::ATTRIBUTE,
                    'label' => __('Item belongs to complete bundle deal set'),
                ],
            ],
        ];

        return $result;
    }
}
