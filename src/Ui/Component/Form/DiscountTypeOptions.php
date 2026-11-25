<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Form;

use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Data\OptionSourceInterface;

class DiscountTypeOptions implements OptionSourceInterface
{
    /**
     * Returns the slot discount types as options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Slot::DISCOUNT_NONE, 'label' => __('No discount')],
            ['value' => Slot::DISCOUNT_PERCENT, 'label' => __('Percentage of the item price')],
            ['value' => Slot::DISCOUNT_FIXED, 'label' => __('Fixed amount off per item')],
        ];
    }
}
