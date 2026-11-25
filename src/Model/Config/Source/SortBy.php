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
 * Candidate sort orders for a slot; the first candidate is the default product shown.
 */
class SortBy implements OptionSourceInterface
{
    public const POSITION = 'position';
    public const PRICE_ASC = 'price_asc';
    public const PRICE_DESC = 'price_desc';
    public const NAME = 'name';
    public const NEWEST = 'newest';

    /**
     * Returns the candidate sort orders as options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::POSITION, 'label' => __('Manual position, then name')],
            ['value' => self::PRICE_ASC, 'label' => __('Price, low to high')],
            ['value' => self::PRICE_DESC, 'label' => __('Price, high to low')],
            ['value' => self::NAME, 'label' => __('Name')],
            ['value' => self::NEWEST, 'label' => __('Newest first')],
        ];
    }
}
