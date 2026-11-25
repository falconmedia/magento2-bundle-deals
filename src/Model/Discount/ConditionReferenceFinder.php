<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Discount;

use FalconMedia\BundleDeals\Model\Rule\Condition\ItemInSet;
use FalconMedia\BundleDeals\Model\Rule\Condition\SetComplete;

/**
 * Reads which bundle deal rule ids a serialized cart price rule condition tree requires.
 *
 * Only positive ("is") references count, and only when the path from the root to the condition
 * is an "all ... true" combination, so the bundle condition is required for the rule to apply.
 */
class ConditionReferenceFinder
{
    /**
     * Finds the bundle deals that a condition tree requires to be complete.
     *
     * @param array<string,mixed>|null $tree decoded conditions_serialized or actions_serialized
     * @return int[]
     */
    public function findRequiredRuleIds(?array $tree): array
    {
        if (!$tree) {
            return [];
        }

        return array_values(array_unique($this->walk($tree)));
    }

    /**
     * Collects the referenced bundle deal ids of a condition node and its children.
     *
     * @param array<string,mixed> $node
     * @return int[]
     */
    private function walk(array $node): array
    {
        $type = (string) ($node['type'] ?? '');
        if ($type === SetComplete::class || $type === ItemInSet::class) {
            $operator = (string) ($node['operator'] ?? '==');

            return $operator === '==' && is_numeric($node['value'] ?? null) ? [(int) $node['value']] : [];
        }

        $children = $node['conditions'] ?? [];
        if (!is_array($children) || !$children) {
            return [];
        }
        $aggregator = (string) ($node['aggregator'] ?? 'all');
        $value = (string) ($node['value'] ?? '1');
        if ($aggregator !== 'all' || $value !== '1') {
            return [];
        }

        $ids = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $ids[] = $this->walk($child);
            }
        }

        return array_merge([], ...$ids);
    }
}
