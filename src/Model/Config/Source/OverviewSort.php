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
 * Sort orders of the bundle deal overview page.
 */
class OverviewSort implements OptionSourceInterface
{
    public const POPULAR = 'popular';
    public const SAVING = 'saving';
    public const PRICE_ASC = 'price_asc';
    public const PRICE_DESC = 'price_desc';
    public const NEWEST = 'newest';

    /**
     * Returns the sort orders.
     *
     * @return array<int,array{value:string, label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::POPULAR, 'label' => __('Popular')],
            ['value' => self::SAVING, 'label' => __('Biggest saving')],
            ['value' => self::PRICE_ASC, 'label' => __('Price, low to high')],
            ['value' => self::PRICE_DESC, 'label' => __('Price, high to low')],
            ['value' => self::NEWEST, 'label' => __('Newest first')],
        ];
    }

    /**
     * Returns the sort order codes.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return array_column($this->toOptionArray(), 'value');
    }
}
