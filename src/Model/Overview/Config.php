<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Overview;

use FalconMedia\BundleDeals\Model\Config as ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the overview page settings (Stores > Configuration > Catalog > Bundle Deals > Overview page).
 */
class Config
{
    public const XML_PATH_ENABLED = 'falconmedia_bundle_deals/overview/enabled';
    public const XML_PATH_URL_KEY = 'falconmedia_bundle_deals/overview/url_key';
    public const XML_PATH_PAGE_TITLE = 'falconmedia_bundle_deals/overview/page_title';
    public const XML_PATH_H1 = 'falconmedia_bundle_deals/overview/h1';
    public const XML_PATH_META_TITLE = 'falconmedia_bundle_deals/overview/meta_title';
    public const XML_PATH_META_DESCRIPTION = 'falconmedia_bundle_deals/overview/meta_description';
    public const XML_PATH_INTRO = 'falconmedia_bundle_deals/overview/intro';
    public const XML_PATH_CMS_BLOCK = 'falconmedia_bundle_deals/overview/cms_block';
    public const XML_PATH_ITEMS_PER_PAGE = 'falconmedia_bundle_deals/overview/items_per_page';
    public const XML_PATH_DEFAULT_SORT = 'falconmedia_bundle_deals/overview/default_sort';
    public const XML_PATH_SPORT_ATTRIBUTE = 'falconmedia_bundle_deals/overview/sport_attribute';
    public const XML_PATH_BRAND_ATTRIBUTE = 'falconmedia_bundle_deals/overview/brand_attribute';
    public const XML_PATH_BRAND_PARAM = 'falconmedia_bundle_deals/overview/brand_param';

    public const DEFAULT_URL_KEY = 'bundles';
    public const DEFAULT_ITEMS_PER_PAGE = 24;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    public function __construct(ScopeConfigInterface $scopeConfig, ModuleConfig $moduleConfig)
    {
        $this->scopeConfig = $scopeConfig;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Tells whether the overview page is enabled; it needs the module to be enabled too.
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->moduleConfig->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns the URL key of the overview page, without slashes.
     */
    public function getUrlKey(?int $storeId = null): string
    {
        $urlKey = trim($this->getValue(self::XML_PATH_URL_KEY, $storeId), '/ ');

        return $urlKey !== '' ? $urlKey : self::DEFAULT_URL_KEY;
    }

    public function getPageTitle(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_PAGE_TITLE, $storeId);
    }

    /**
     * Returns the H1, falling back to the page title.
     */
    public function getH1(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_H1, $storeId) ?: $this->getPageTitle($storeId);
    }

    /**
     * Returns the meta title, falling back to the page title.
     */
    public function getMetaTitle(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_META_TITLE, $storeId) ?: $this->getPageTitle($storeId);
    }

    public function getMetaDescription(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_META_DESCRIPTION, $storeId);
    }

    /**
     * Returns the intro text (HTML allowed, edited by admins).
     */
    public function getIntro(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_INTRO, $storeId);
    }

    public function getCmsBlock(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_CMS_BLOCK, $storeId);
    }

    /**
     * Returns the number of cards per page.
     */
    public function getItemsPerPage(?int $storeId = null): int
    {
        $value = (int) $this->getValue(self::XML_PATH_ITEMS_PER_PAGE, $storeId);

        return $value > 0 ? min($value, 120) : self::DEFAULT_ITEMS_PER_PAGE;
    }

    public function getDefaultSort(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_DEFAULT_SORT, $storeId) ?: 'popular';
    }

    public function getSportAttribute(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_SPORT_ATTRIBUTE, $storeId) ?: 'sport';
    }

    /**
     * Name of the URL parameter of the brand filter, for example "brand" in /bundles?brand=venum.
     */
    public function getBrandParam(?int $storeId = null): string
    {
        $param = strtolower($this->getValue(self::XML_PATH_BRAND_PARAM, $storeId));

        $reserved = ['sport', 'sort', 'p'];

        return preg_match('/^[a-z][a-z0-9_]{0,31}$/', $param) === 1 && !in_array($param, $reserved, true)
            ? $param
            : 'brand';
    }

    public function getBrandAttribute(?int $storeId = null): string
    {
        return $this->getValue(self::XML_PATH_BRAND_ATTRIBUTE, $storeId) ?: 'manufacturer';
    }

    private function getValue(string $path, ?int $storeId): string
    {
        return trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
