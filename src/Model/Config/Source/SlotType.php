<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Slot types: how the shopper deals with a slot on the set page.
 */
class SlotType implements OptionSourceInterface
{
    /**
     * Returns the slot types as options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Slot::TYPE_VARIABLE, 'label' => __('Variable: the shopper picks a product')],
            ['value' => Slot::TYPE_FIXED, 'label' => __('Fixed: always the first product')],
            ['value' => Slot::TYPE_OPTIONAL, 'label' => __('Optional: the shopper may leave it out')],
        ];
    }
}
