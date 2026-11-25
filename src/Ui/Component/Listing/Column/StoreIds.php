<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Listing\Column;

use Magento\Store\Ui\Component\Listing\Column\Store;

/**
 * Store column for a comma separated store id value.
 */
class StoreIds extends Store
{
    /**
     * Turns the comma separated store ids of a row into a list.
     *
     * @param array<string,mixed> $item
     * @return string
     */
    protected function prepareItem(array $item)
    {
        if (isset($item[$this->storeKey]) && is_string($item[$this->storeKey])) {
            $item[$this->storeKey] = array_map('intval', explode(',', $item[$this->storeKey]));
        }

        return parent::prepareItem($item);
    }
}
