<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

/**
 * Options of the variation attribute for the fallback values multiselect (no empty option).
 */
class VariationFallbackOptions extends VariationOptions
{
    /**
     * Returns the variation options without the leading empty option.
     *
     * @return array<int,array{value:string, label:string}>
     */
    public function toOptionArray(): array
    {
        return array_slice(parent::toOptionArray(), 1);
    }
}
