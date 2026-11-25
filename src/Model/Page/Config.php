<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Page;

use FalconMedia\BundleDeals\Model\Config as ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the set page settings (Stores > Configuration > Catalog > Bundle Deals > Set page).
 */
class Config
{
    public const REVIEWS_ITEMS = 'items';
    public const REVIEWS_NONE = 'none';

    private const PATH = 'falconmedia_bundle_deals/set_page/';
    private const DEFAULT_PREFIX = 'set';
    private const DEFAULT_MORE_SETS = 4;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleConfig $moduleConfig
    ) {
    }

    /**
     * Tells whether set pages are served; they need the module to be enabled too.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->moduleConfig->isEnabled($storeId) && $this->isFlag('enabled', $storeId);
    }

    public function isIndexable(?int $storeId = null): bool
    {
        return $this->isFlag('indexable', $storeId);
    }

    /**
     * Returns the URL prefix without slashes.
     */
    public function getUrlPrefix(?int $storeId = null): string
    {
        $prefix = trim($this->getValue('url_prefix', $storeId), '/ ');

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    /**
     * Returns how many other sets the page suggests.
     */
    public function getMoreSetsCount(?int $storeId = null): int
    {
        $value = $this->getValue('more_sets_count', $storeId);

        return $value === '' ? self::DEFAULT_MORE_SETS : max(0, min(12, (int) $value));
    }

    public function isStickyBar(?int $storeId = null): bool
    {
        return $this->isFlag('sticky_bar', $storeId);
    }

    /**
     * Returns the review source, one of the REVIEWS_ constants.
     */
    public function getReviewSource(?int $storeId = null): string
    {
        return $this->getValue('review_source', $storeId) === self::REVIEWS_NONE
            ? self::REVIEWS_NONE
            : self::REVIEWS_ITEMS;
    }

    public function getDeliveryText(?int $storeId = null): string
    {
        return $this->getValue('delivery_text', $storeId);
    }

    public function getDeliveryCmsBlock(?int $storeId = null): string
    {
        return $this->getValue('delivery_cms_block', $storeId);
    }

    public function getReturnText(?int $storeId = null): string
    {
        return $this->getValue('return_text', $storeId);
    }

    public function getReturnCmsBlock(?int $storeId = null): string
    {
        return $this->getValue('return_cms_block', $storeId);
    }

    /**
     * Returns the CMS block identifier with the size advice.
     */
    public function getSizeChartCmsBlock(?int $storeId = null): string
    {
        return $this->getValue('sizechart_cms_block', $storeId);
    }

    private function isFlag(string $field, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function getValue(string $field, ?int $storeId): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
