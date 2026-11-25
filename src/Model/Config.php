<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the Stores > Configuration > Catalog > Bundle Deals settings.
 */
class Config
{
    public const XML_PATH_ENABLED = 'falconmedia_bundle_deals/general/enabled';
    public const XML_PATH_ANCHOR_ID = 'falconmedia_bundle_deals/general/anchor_id';
    public const XML_PATH_BLOCK_TITLE = 'falconmedia_bundle_deals/general/block_title';
    public const XML_PATH_MAX_SLOTS = 'falconmedia_bundle_deals/general/max_slots';
    public const XML_PATH_MAX_CANDIDATES = 'falconmedia_bundle_deals/general/max_candidates';
    public const XML_PATH_POSITION = 'falconmedia_bundle_deals/general/position';
    public const XML_PATH_SHOW_SPLIT_SHIPMENT = 'falconmedia_bundle_deals/general/show_split_shipment';
    public const XML_PATH_COLOR_MATCH_ENABLED = 'falconmedia_bundle_deals/color_match/enabled';
    public const XML_PATH_COLOR_MATCH_ATTRIBUTE = 'falconmedia_bundle_deals/color_match/attribute';
    public const XML_PATH_COLOR_SWATCH_ATTRIBUTE = 'falconmedia_bundle_deals/color_match/swatch_attribute';
    public const XML_PATH_COLOR_MATCH_FALLBACKS = 'falconmedia_bundle_deals/color_match/fallback_colors';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Returns the id of the bundle deal section on the product page, used as URL fragment.
     */
    public function getAnchorId(?int $storeId = null): string
    {
        $id = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ANCHOR_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $id) === 1 ? $id : 'bundle-deal';
    }

    public function getBlockTitle(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_BLOCK_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getMaxSlots(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_SLOTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getMaxCandidates(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_CANDIDATES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getPosition(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_POSITION, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether the block may say that the bundle arrives in two shipments.
     */
    public function isSplitShipmentShown(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_SPLIT_SHIPMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether the other items of a bundle on a product page start in the colour of the viewed product.
     */
    public function isColorMatchEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_COLOR_MATCH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Code of the product attribute that holds the colour.
     */
    public function getColorMatchAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_COLOR_MATCH_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $code !== '' ? $code : 'color';
    }

    /**
     * Code of the attribute whose option swatches give the colour dots; the colour attribute when not set.
     */
    public function getSwatchAttribute(?int $storeId = null): string
    {
        $code = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_COLOR_SWATCH_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $code !== '' ? $code : $this->getColorMatchAttribute($storeId);
    }

    /**
     * Option ids of the colours to use when no item has the colour of the viewed product, in attribute option order.
     *
     * @return int[]
     */
    public function getColorMatchFallbackOptionIds(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_COLOR_MATCH_FALLBACKS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return array_values(array_map('intval', array_filter(explode(',', $value), static fn (string $part): bool => $part !== '')));
    }
}
