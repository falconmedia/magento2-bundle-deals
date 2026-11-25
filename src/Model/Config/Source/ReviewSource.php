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
 * Where the reviews of a set page come from.
 */
class ReviewSource implements OptionSourceInterface
{
    public const NONE = 'none';
    public const ITEMS = 'items';

    /**
     * Returns the review sources as options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ITEMS, 'label' => __('Reviews of the set items')],
            ['value' => self::NONE, 'label' => __('No reviews')],
        ];
    }
}
