<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Overview;

use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\ViewModel\Overview;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Card listing of the bundle deal overview page; its cache tags make the page refresh when a deal changes.
 */
class Listing extends Template implements IdentityInterface
{
    /**
     * @var Overview
     */
    private $overview;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(Context $context, Overview $overview, array $data = [])
    {
        $this->overview = $overview;
        parent::__construct($context, $data);
    }

    public function getOverview(): Overview
    {
        return $this->overview;
    }

    /**
     * Returns the cache tags of the rendered bundle deals.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return array_values(array_unique(array_merge([Rule::CACHE_TAG], $this->overview->getIdentities())));
    }
}
