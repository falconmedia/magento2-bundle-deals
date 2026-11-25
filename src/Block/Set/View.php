<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Set;

use FalconMedia\BundleDeals\ViewModel\SetPage;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Set page content; its cache tags make the page refresh when the deal or one of its products changes.
 */
class View extends Template implements IdentityInterface
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly SetPage $setPage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSetPage(): SetPage
    {
        return $this->setPage;
    }

    /**
     * Returns the cache tags of the rendered set.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return array_values(array_unique($this->setPage->getIdentities()));
    }
}
