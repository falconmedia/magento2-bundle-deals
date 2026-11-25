<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Storefront;

use FalconMedia\BundleDeals\Api\DeliveryTimeProviderInterface;
use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\Import\Amasty\ColorGroupResolver;
use FalconMedia\BundleDeals\Model\Overview\CardBuilder;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Colour variants of bundle deals that share a colour group, for the overview cards and the product page.
 */
class ColorGroupProvider
{
    private const MAX_IMAGES = 3;

    /**
     * @var BundleDataProvider
     */
    private $dataProvider;

    /**
     * @var ActiveRuleProvider
     */
    private $activeRuleProvider;

    /**
     * @var ProductAttributeValues
     */
    private $attributeValues;

    /**
     * @var DeliveryTimeProviderInterface
     */
    private $deliveryTimeProvider;

    /**
     * @var CardBuilder
     */
    private $cardBuilder;

    /**
     * @var ColorGroupResolver
     */
    private $nameParser;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        BundleDataProvider $dataProvider,
        ActiveRuleProvider $activeRuleProvider,
        ProductAttributeValues $attributeValues,
        DeliveryTimeProviderInterface $deliveryTimeProvider,
        CardBuilder $cardBuilder,
        ColorGroupResolver $nameParser,
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        $this->dataProvider = $dataProvider;
        $this->activeRuleProvider = $activeRuleProvider;
        $this->attributeValues = $attributeValues;
        $this->deliveryTimeProvider = $deliveryTimeProvider;
        $this->cardBuilder = $cardBuilder;
        $this->nameParser = $nameParser;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * Colour variants of the colour group a bundle deal belongs to; empty when it has no group of two or more.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVariantsForRule(int $ruleId, int $customerGroupId): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $rule = $this->activeRuleProvider->getRule($ruleId, $storeId);
        $group = $rule !== null ? (string) $rule->getColorGroup() : '';
        if ($group === '') {
            return [];
        }
        $memberIds = [];
        foreach ($this->activeRuleProvider->getRules($storeId) as $id => $member) {
            if ((string) $member->getColorGroup() === $group) {
                $memberIds[] = (int) $id;
            }
        }
        if (count($memberIds) < 2) {
            return [];
        }
        $bundles = $this->dataProvider->getOverviewBundles($customerGroupId, 0, true, $memberIds);
        $entryIds = array_filter(array_map(static function (array $bundle): int {
            return (int) ($bundle['entryProductId'] ?? 0);
        }, $bundles));
        $deliveries = $this->getDeliveries($entryIds, $storeId);
        $variants = $this->getVariantsByGroup(
            $bundles,
            $storeId,
            array_map(static fn (array $delivery): string => $delivery['label'], $deliveries),
            array_map(static fn (array $delivery): bool => $delivery['direct'], $deliveries)
        );

        return $variants[$group] ?? [];
    }

    /**
     * Colour variants per colour group, ordered by colour sort order; groups with one member are left out.
     *
     * @param array<int,array<string,mixed>> $bundles overview bundles, unavailable ones included
     * @param array<int,string> $deliveryTexts keyed by product id
     * @param array<int,bool> $deliveryDirect keyed by product id, true when the product ships from own stock
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function getVariantsByGroup(
        array $bundles,
        int $storeId,
        array $deliveryTexts = [],
        array $deliveryDirect = []
    ): array {
        $grouped = [];
        $entryIds = [];
        foreach ($bundles as $bundle) {
            $group = (string) ($bundle['colorGroup'] ?? '');
            if ($group === '') {
                continue;
            }
            $grouped[$group][] = $bundle;
            if (!empty($bundle['entryProductId'])) {
                $entryIds[] = (int) $bundle['entryProductId'];
            }
        }
        $grouped = array_filter($grouped, static function (array $members): bool {
            return count($members) > 1;
        });
        if (!$grouped) {
            return [];
        }

        $colorLabels = $this->attributeValues->getOptionLabels($this->config->getColorMatchAttribute($storeId), $storeId);
        $swatchLabels = $this->attributeValues->getOptionLabels($this->config->getSwatchAttribute($storeId), $storeId);
        $swatchOptions = $this->attributeValues->getOptionIds($this->config->getSwatchAttribute($storeId), $entryIds, $storeId);
        $swatchOptionIds = [];
        foreach ($swatchOptions as $optionIds) {
            foreach ($optionIds as $optionId) {
                $swatchOptionIds[] = $optionId;
            }
        }
        $hexes = $this->attributeValues->getSwatchHexes($swatchOptionIds, $storeId);

        $result = [];
        foreach ($grouped as $group => $members) {
            usort($members, static function (array $a, array $b): int {
                $orderA = $a['colorSortOrder'] ?? PHP_INT_MAX;
                $orderB = $b['colorSortOrder'] ?? PHP_INT_MAX;

                return [$orderA, $a['ruleId']] <=> [$orderB, $b['ruleId']];
            });
            foreach ($members as $bundle) {
                $entryId = (int) ($bundle['entryProductId'] ?? 0);
                $swatchOptionId = $swatchOptions[$entryId][0] ?? null;
                $result[$group][] = $this->buildVariant(
                    $bundle,
                    $colorLabels,
                    $swatchOptionId !== null ? ($swatchLabels[$swatchOptionId] ?? '') : '',
                    $swatchOptionId !== null ? ($hexes[$swatchOptionId] ?? null) : null,
                    $deliveryTexts[$entryId] ?? '',
                    $deliveryDirect[$entryId] ?? false
                );
            }
        }

        return $result;
    }

    /**
     * Delivery labels keyed by product id, for products with a known delivery time.
     *
     * @param int[] $productIds
     * @return array<int,string>
     */
    public function getDeliveryTexts(array $productIds, int $storeId): array
    {
        return array_map(
            static fn (array $delivery): string => $delivery['label'],
            $this->getDeliveries($productIds, $storeId)
        );
    }

    /**
     * Delivery label and direct flag keyed by product id, for products with a known delivery time.
     *
     * @param int[] $productIds
     * @return array<int,array{label: string, direct: bool}>
     */
    public function getDeliveries(array $productIds, int $storeId): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) {
            return [];
        }
        $deliveries = [];
        foreach ($this->deliveryTimeProvider->getForProductIds($productIds, $storeId) as $productId => $delivery) {
            $label = trim((string) ($delivery['label'] ?? ''));
            if ($label !== '') {
                $deliveries[(int) $productId] = ['label' => $label, 'direct' => !empty($delivery['direct'])];
            }
        }

        return $deliveries;
    }

    /**
     * Builds one colour variant.
     *
     * @param array<string,mixed> $bundle
     * @param array<int,string> $colorLabels
     * @return array<string,mixed>
     */
    private function buildVariant(
        array $bundle,
        array $colorLabels,
        string $swatchLabel,
        ?string $swatchHex,
        string $deliveryText,
        bool $deliveryDirect
    ): array {
        $available = !empty($bundle['available']);
        $colorOptionId = isset($bundle['colorOptionId']) ? (int) $bundle['colorOptionId'] : null;
        $label = $colorOptionId !== null ? $this->dedupeLabel($colorLabels[$colorOptionId] ?? '') : '';
        if ($label === '') {
            $label = $swatchLabel !== '' ? $swatchLabel : $this->nameParser->splitName((string) $bundle['title'])[1];
        }
        $totals = $available
            ? $this->cardBuilder->getTotals($bundle)
            : ['setPrice' => null, 'sumPrice' => null, 'saving' => null];
        $entrySlot = null;
        $images = [];
        foreach ((array) ($bundle['slots'] ?? []) as $slot) {
            if ($entrySlot === null && !empty($slot['isEntry'])) {
                $entrySlot = $slot;
            }
            $first = $slot['candidates'][0] ?? [];
            $image = ($first['imageCard'] ?? '') ?: ($first['imageLarge'] ?? '') ?: ($first['image'] ?? null);
            if (!empty($image) && count($images) < self::MAX_IMAGES) {
                $images[] = (string) $image;
            }
        }

        return [
            'ruleId' => (int) $bundle['ruleId'],
            'label' => $label !== '' ? $label : (string) $bundle['title'],
            'colorOptionId' => $colorOptionId,
            'swatchHex' => $swatchHex,
            'imageUrl' => $entrySlot !== null
                ? (($entrySlot['candidates'][0]['imageCard'] ?? '') ?: ($entrySlot['candidates'][0]['imageLarge'] ?? '')
                    ?: ($entrySlot['candidates'][0]['image'] ?? null))
                : null,
            'images' => $images,
            'url' => $bundle['url'] ?? null,
            'setPrice' => $totals['setPrice'],
            'sumPrice' => $totals['sumPrice'],
            'saving' => $totals['saving'],
            'inStock' => $available,
            'deliverable' => $available,
            'deliveryText' => $available ? $deliveryText : '',
            'deliveryDirect' => $available && $deliveryDirect,
        ];
    }

    /**
     * Collapse repeated parts of a colour option label, because catalog options contain values like "Wit, Wit".
     */
    private function dedupeLabel(string $label): string
    {
        return implode(', ', array_unique(array_map('trim', explode(',', $label))));
    }
}
