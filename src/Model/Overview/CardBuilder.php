<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Overview;

use FalconMedia\BundleDeals\Model\Config\Source\OverviewSort;

/**
 * Turns overview bundles into overview cards: one card per colour group, with totals, filters and sorting.
 */
class CardBuilder
{
    public const FILTER_SPORT = 'sport';
    public const FILTER_BRAND = 'brand';

    private const MAX_IMAGES = 3;

    /**
     * Set price of the default products: the sum without and with the slot discounts, both at the prices the shopper pays.
     *
     * @param array<string,mixed> $bundle
     * @return array{sumPrice:float, setPrice:float, saving:float, maxDiscountPercent:int}
     */
    public function getTotals(array $bundle): array
    {
        $sum = 0.0;
        $set = 0.0;
        $maxPercent = 0.0;
        foreach ((array) ($bundle['slots'] ?? []) as $slot) {
            $candidate = $slot['candidates'][0] ?? [];
            $price = (float) ($candidate['finalPrice'] ?? 0);
            $sum += $price;
            $amount = (float) ($slot['discount']['amount'] ?? 0);
            $type = (string) ($slot['discount']['type'] ?? '');
            $discounted = $price;
            $onSpecialPrice = !empty($slot['discount']['excludeSpecialPrice'])
                && $price < (float) ($candidate['regularPrice'] ?? 0);
            if ($onSpecialPrice) {
                $discounted = $price;
            } elseif ($type === 'percent') {
                $discounted = $price * (1 - min($amount, 100) / 100);
            } elseif ($type === 'fixed') {
                $discounted = max(0.0, $price - $amount);
            }
            if ($price > 0) {
                $maxPercent = max($maxPercent, ($price - $discounted) / $price * 100);
            }
            $set += $discounted;
        }
        $sum = round($sum, 2);
        $set = round($set, 2);

        return [
            'sumPrice' => $sum,
            'setPrice' => $set,
            'saving' => round(max(0.0, $sum - $set), 2),
            'maxDiscountPercent' => (int) round($maxPercent),
        ];
    }

    /**
     * Builds one card per colour group and per ungrouped bundle; groups without an available member are left out.
     *
     * Meta holds per rule id "brand" (label or null), "brandSlug", "sports" (slug => label), "deliveryText"
     * and "deliveryDirect".
     *
     * @param array<int,array<string,mixed>> $bundles in display order, as getOverviewBundles returns them
     * @param array<int,array<string,mixed>> $meta keyed by rule id
     * @param array<string,array<int,array<string,mixed>>> $variantsByGroup keyed by colour group
     * @return array<int,array<string,mixed>>
     */
    public function buildCards(array $bundles, array $meta, array $variantsByGroup = []): array
    {
        $representatives = [];
        $position = 0;
        foreach ($bundles as $bundle) {
            if (empty($bundle['available'])) {
                continue;
            }
            $group = (string) ($bundle['colorGroup'] ?? '');
            $key = $group !== '' ? 'group:' . $group : 'rule:' . (int) $bundle['ruleId'];
            if (!isset($representatives[$key])) {
                $representatives[$key] = ['bundle' => $bundle, 'position' => $position++];
                continue;
            }
            if ($this->colorOrder($bundle) < $this->colorOrder($representatives[$key]['bundle'])) {
                $representatives[$key]['bundle'] = $bundle;
            }
        }

        $cards = [];
        foreach ($representatives as $entry) {
            $bundle = $entry['bundle'];
            $ruleId = (int) $bundle['ruleId'];
            $group = (string) ($bundle['colorGroup'] ?? '');
            $ruleMeta = $meta[$ruleId] ?? [];
            $images = [];
            $names = [];
            foreach ((array) $bundle['slots'] as $slot) {
                $candidate = $slot['candidates'][0] ?? [];
                $names[] = (string) ($candidate['name'] ?? $slot['title'] ?? '');
                // The 75px thumbnail is far too small for the card; use the dedicated card image size.
                $image = (string) ($candidate['imageCard'] ?? '') ?: (string) ($candidate['imageLarge'] ?? '')
                    ?: (string) ($candidate['image'] ?? '');
                if ($image !== '' && count($images) < self::MAX_IMAGES) {
                    $images[] = $image;
                }
            }
            $cards[] = [
                'ruleId' => $ruleId,
                'title' => (string) (($bundle['name'] ?? '') !== '' ? $bundle['name'] : $bundle['title']),
                'brand' => $ruleMeta['brand'] ?? null,
                'brandSlug' => (string) ($ruleMeta['brandSlug'] ?? ''),
                'sports' => (array) ($ruleMeta['sports'] ?? []),
                'url' => (string) $bundle['url'],
                'images' => $images,
                'itemCount' => count($names),
                'itemNames' => $names,
                'inStock' => true,
                'deliveryText' => (string) ($ruleMeta['deliveryText'] ?? ''),
                'deliveryDirect' => !empty($ruleMeta['deliveryDirect']),
                'colorGroup' => $group !== '' ? $group : null,
                'variants' => $group !== '' ? ($variantsByGroup[$group] ?? []) : [],
                'priority' => (int) ($bundle['priority'] ?? 0),
                'position' => $entry['position'],
            ] + $this->getTotals($bundle);
        }

        return $cards;
    }

    /**
     * Keeps the cards that match every given filter; filter values are slugs.
     *
     * @param array<int,array<string,mixed>> $cards
     * @param array<string,string> $filters
     * @return array<int,array<string,mixed>>
     */
    public function filterCards(array $cards, array $filters): array
    {
        return array_values(array_filter($cards, function (array $card) use ($filters): bool {
            return $this->matches($card, $filters);
        }));
    }

    /**
     * Filter options with card counts; each filter is counted with the other filters applied.
     *
     * @param array<int,array<string,mixed>> $cards
     * @param array<string,string> $filters
     * @return array<string,array<string,array{label:string, count:int}>>
     */
    public function getFacets(array $cards, array $filters): array
    {
        $facets = [self::FILTER_SPORT => [], self::FILTER_BRAND => []];
        foreach (array_keys($facets) as $facet) {
            $others = $filters;
            unset($others[$facet]);
            foreach ($cards as $card) {
                if (!$this->matches($card, $others)) {
                    continue;
                }
                $values = $facet === self::FILTER_SPORT
                    ? $card['sports']
                    : ($card['brandSlug'] !== '' ? [$card['brandSlug'] => (string) $card['brand']] : []);
                foreach ($values as $slug => $label) {
                    if (!isset($facets[$facet][$slug])) {
                        $facets[$facet][$slug] = ['label' => (string) $label, 'count' => 0];
                    }
                    $facets[$facet][$slug]['count']++;
                }
            }
            uasort($facets[$facet], static function (array $a, array $b): int {
                return strnatcasecmp($a['label'], $b['label']);
            });
        }

        return $facets;
    }

    /**
     * Sorts cards by one of the overview sort codes; ties keep the display order.
     *
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array<string,mixed>>
     */
    public function sortCards(array $cards, string $sort): array
    {
        usort($cards, static function (array $a, array $b) use ($sort): int {
            switch ($sort) {
                case OverviewSort::SAVING:
                    $result = $b['saving'] <=> $a['saving'];
                    break;
                case OverviewSort::PRICE_ASC:
                    $result = $a['setPrice'] <=> $b['setPrice'];
                    break;
                case OverviewSort::PRICE_DESC:
                    $result = $b['setPrice'] <=> $a['setPrice'];
                    break;
                case OverviewSort::NEWEST:
                    $result = $b['ruleId'] <=> $a['ruleId'];
                    break;
                default:
                    $result = 0;
            }

            return $result !== 0 ? $result : $a['position'] <=> $b['position'];
        });

        return $cards;
    }

    /**
     * Tells whether a card matches all filters.
     *
     * @param array<string,mixed> $card
     * @param array<string,string> $filters
     */
    private function matches(array $card, array $filters): bool
    {
        $sport = (string) ($filters[self::FILTER_SPORT] ?? '');
        if ($sport !== '' && !isset($card['sports'][$sport])) {
            return false;
        }
        $brand = (string) ($filters[self::FILTER_BRAND] ?? '');

        return $brand === '' || $card['brandSlug'] === $brand;
    }

    /**
     * Colour sort order of a bundle; bundles without one sort last, then by rule id.
     *
     * @param array<string,mixed> $bundle
     * @return array{0:int, 1:int}
     */
    private function colorOrder(array $bundle): array
    {
        $order = $bundle['colorSortOrder'] ?? null;

        return [$order === null ? PHP_INT_MAX : (int) $order, (int) $bundle['ruleId']];
    }
}
