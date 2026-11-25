<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Plugin\Catalog;

use Magento\Catalog\Block\Product\View\Details;

/**
 * Renders the bundle deal blocks for the "above/below the product details" positions around the details block,
 * so the theme layout of the product page does not have to change.
 */
class RenderBundleDealAroundDetails
{
    private const BEFORE = 'falconmedia.bundledeals.pdp.before_details';
    private const AFTER = 'falconmedia.bundledeals.pdp.after_details';

    /**
     * Wraps the product details HTML with the bundle deal blocks.
     *
     * @param string $result
     * @return string
     */
    public function afterToHtml(Details $subject, $result)
    {
        $layout = $subject->getLayout();
        if (!$layout->getBlock(self::BEFORE) && !$layout->getBlock(self::AFTER)) {
            return $result;
        }

        return $layout->renderElement(self::BEFORE, false)
            . $result
            . $layout->renderElement(self::AFTER, false);
    }
}
