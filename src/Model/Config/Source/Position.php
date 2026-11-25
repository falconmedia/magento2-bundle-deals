<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product page positions; each one maps to a block instance in catalog_product_view_hyva.xml.
 */
class Position implements OptionSourceInterface
{
    public const BUYBOX = 'buybox';
    public const BEFORE_DETAILS = 'before_details';
    public const AFTER_DETAILS = 'after_details';

    /**
     * Returns the block positions as options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BUYBOX, 'label' => __('Below the add to cart button')],
            ['value' => self::BEFORE_DETAILS, 'label' => __('Above the product details')],
            ['value' => self::AFTER_DETAILS, 'label' => __('Below the product details')],
        ];
    }
}
