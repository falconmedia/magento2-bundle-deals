<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Storefront;

use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;

/**
 * Orders the alternatives of a bundle slot so the item in the colour of the viewed product comes first.
 *
 * Colours are compared by label, so equal colours match even when they are separate attribute options.
 * Without a match the configured fallback colours apply, in order; otherwise the order stays as it is.
 */
class ColorMatcher
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductAttributeValues $attributeValues
    ) {
    }

    /**
     * Candidate ids, best colour match first; the original order is kept within equal matches.
     *
     * @param int[] $candidateIds
     * @return int[]
     */
    public function sortCandidateIds(array $candidateIds, int $viewedProductId, int $storeId): array
    {
        if (count($candidateIds) < 2 || !$this->config->isColorMatchEnabled($storeId)) {
            return $candidateIds;
        }
        $attribute = $this->config->getColorMatchAttribute($storeId);
        $labels = $this->attributeValues->getOptionLabels($attribute, $storeId);
        $optionIds = $this->attributeValues->getOptionIds(
            $attribute,
            array_merge([$viewedProductId], $candidateIds),
            $storeId
        );
        if (!$labels || !$optionIds) {
            return $candidateIds;
        }

        $viewed = $this->productLabels($optionIds[$viewedProductId] ?? [], $labels);
        $fallbacks = [];
        foreach ($this->config->getColorMatchFallbackOptionIds($storeId) as $fallbackOptionId) {
            if (isset($labels[$fallbackOptionId])) {
                $fallbacks[] = $this->normalise($labels[$fallbackOptionId]);
            }
        }

        $ranks = [];
        foreach ($candidateIds as $position => $candidateId) {
            $candidate = $this->productLabels($optionIds[$candidateId] ?? [], $labels);
            $ranks[$candidateId] = $this->rank($candidate, $viewed, $fallbacks);
        }

        $positions = array_flip($candidateIds);
        usort($candidateIds, static function (int $a, int $b) use ($ranks, $positions): int {
            return [$ranks[$a], $positions[$a]] <=> [$ranks[$b], $positions[$b]];
        });

        return $candidateIds;
    }

    /**
     * Rank of a candidate: 0 has exactly the viewed colour(s), 1 shares one, 2.. is the fallback position, then no match.
     *
     * @param string[] $candidate
     * @param string[] $viewed
     * @param string[] $fallbacks
     */
    private function rank(array $candidate, array $viewed, array $fallbacks): int
    {
        if ($viewed) {
            $same = array_values(array_unique($candidate));
            $wanted = array_values(array_unique($viewed));
            sort($same);
            sort($wanted);
            if ($same === $wanted) {
                return 0;
            }
            if (array_intersect($candidate, $viewed)) {
                return 1;
            }
        }
        foreach ($fallbacks as $index => $fallback) {
            if (in_array($fallback, $candidate, true)) {
                return $index + 2;
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Normalised colour labels of a product.
     *
     * @param int[] $optionIds
     * @param array<int,string> $labels
     * @return string[]
     */
    private function productLabels(array $optionIds, array $labels): array
    {
        $result = [];
        foreach ($optionIds as $optionId) {
            if (isset($labels[$optionId])) {
                $result[] = $this->normalise($labels[$optionId]);
            }
        }

        return $result;
    }

    /**
     * Lower-cases a label and treats comma, dash and slash as one separator, so "Zwart, Wit" equals "zwart/wit".
     */
    private function normalise(string $label): string
    {
        return (string) preg_replace('/\s*[\/,-]\s*/u', '/', mb_strtolower(trim($label)));
    }
}
