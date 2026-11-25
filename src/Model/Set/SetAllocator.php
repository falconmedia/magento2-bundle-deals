<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Set;

use FalconMedia\BundleDeals\Model\Slot;

/**
 * Divides the cart units over the complete sets of all bundle deals.
 *
 * Every unit counts for one set of one deal only. Among all possible divisions the one with the highest total
 * discount for the customer wins, then the one that covers the most units; on a tie the lowest deal ids win.
 */
class SetAllocator
{
    private const MAX_CANDIDATES_PER_RULE = 250;
    private const MAX_STEPS = 20000;

    /**
     * @var int
     */
    private int $steps = 0;

    /**
     * @var array<int,array{rule_id:int,set:array<int,string>,discount:float,units:int}>
     */
    private array $candidates = [];

    /**
     * @var array{discount:float,units:int,chosen:int[]}
     */
    private array $best = ['discount' => 0.0, 'units' => 0, 'chosen' => []];

    /**
     * Allocates the cart units to complete sets.
     *
     * @param array<int,array<int,int[]>> $rules rule id => role (slot id, in slot order) => eligible product ids
     * @param array<string,array{product_id:int,qty:float,price?:float}> $lines cart line key => line
     * @param array<int,array{type:string,amount:float}> $discounts slot id => slot discount
     * @param int[] $optionalSlotIds slots a set does not need to fill
     * @return array<int,array<string,array<int,int>>> rule id => line key => slot id => covered units
     */
    public function allocate(array $rules, array $lines, array $discounts, array $optionalSlotIds = []): array
    {
        $capacity = [];
        foreach ($lines as $lineKey => $line) {
            $units = (int) floor((float) $line['qty']);
            if ($units > 0) {
                $capacity[(string) $lineKey] = $units;
            }
        }
        if (!$capacity) {
            return [];
        }

        ksort($rules);
        $this->candidates = [];
        foreach ($rules as $ruleId => $roles) {
            foreach ($this->enumerateSets($roles, $lines, $capacity, $optionalSlotIds) as $set) {
                $this->candidates[] = [
                    'rule_id' => (int) $ruleId,
                    'set' => $set,
                    'discount' => $this->getSetDiscount($set, $lines, $discounts),
                    'units' => count($set),
                ];
            }
        }

        $this->steps = 0;
        $this->best = ['discount' => 0.0, 'units' => 0, 'chosen' => []];
        $this->search(0, $capacity, 0.0, 0, []);

        $result = [];
        foreach ($this->best['chosen'] as $index) {
            $candidate = $this->candidates[$index];
            foreach ($candidate['set'] as $slotId => $lineKey) {
                $result[$candidate['rule_id']][$lineKey][$slotId] =
                    ($result[$candidate['rule_id']][$lineKey][$slotId] ?? 0) + 1;
            }
        }
        $this->candidates = [];

        return $result;
    }

    /**
     * Searches the multisets of candidate sets that fit in the cart; candidates are taken in index order.
     *
     * @param array<string,int> $capacity
     * @param int[] $chosen
     */
    private function search(int $start, array $capacity, float $discount, int $units, array $chosen): void
    {
        if (++$this->steps > self::MAX_STEPS) {
            return;
        }
        if ($this->isBetter($discount, $units)) {
            $this->best = ['discount' => $discount, 'units' => $units, 'chosen' => $chosen];
        }

        $count = count($this->candidates);
        for ($index = $start; $index < $count; $index++) {
            $candidate = $this->candidates[$index];
            if (!$this->fits($candidate['set'], $capacity)) {
                continue;
            }
            $next = $capacity;
            foreach ($candidate['set'] as $lineKey) {
                $next[$lineKey]--;
            }
            $chosen[] = $index;
            $this->search(
                $index,
                $next,
                $discount + $candidate['discount'],
                $units + $candidate['units'],
                $chosen
            );
            array_pop($chosen);
        }
    }

    /**
     * Tells whether a division beats the best one so far; the first one found wins a tie.
     */
    private function isBetter(float $discount, int $units): bool
    {
        $difference = round($discount - $this->best['discount'], 4);
        if ($difference !== 0.0) {
            return $difference > 0;
        }

        return $units > $this->best['units'];
    }

    /**
     * Tells whether every line of the set has a unit left.
     *
     * @param array<int,string> $set
     * @param array<string,int> $capacity
     */
    private function fits(array $set, array $capacity): bool
    {
        foreach ($set as $lineKey) {
            if (($capacity[$lineKey] ?? 0) < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * All ways to fill each role of a deal with a different cart line.
     *
     * @param array<int,int[]> $roles
     * @param array<string,array{product_id:int,qty:float,price?:float}> $lines
     * @param array<string,int> $capacity
     * @param int[] $optionalSlotIds roles that may stay empty
     * @return array<int,array<int,string>> list of sets, each role => line key
     */
    private function enumerateSets(array $roles, array $lines, array $capacity, array $optionalSlotIds = []): array
    {
        if (count($roles) < 2) {
            return [];
        }
        $eligible = [];
        foreach ($roles as $role => $productIds) {
            $lookup = array_fill_keys(array_map('intval', $productIds), true);
            $eligible[$role] = [];
            foreach ($lines as $lineKey => $line) {
                if (isset($capacity[(string) $lineKey], $lookup[(int) $line['product_id']])) {
                    $eligible[$role][] = (string) $lineKey;
                }
            }
            if (!$eligible[$role]) {
                if (!in_array((int) $role, $optionalSlotIds, true)) {
                    return [];
                }
                unset($eligible[$role]);
            }
        }
        $required = array_diff(array_keys($roles), $optionalSlotIds);

        $sets = [[]];
        foreach ($eligible as $role => $lineKeys) {
            $extended = [];
            foreach ($sets as $set) {
                foreach ($lineKeys as $lineKey) {
                    if (in_array($lineKey, $set, true)) {
                        continue;
                    }
                    $extended[] = $set + [$role => $lineKey];
                    if (count($extended) >= self::MAX_CANDIDATES_PER_RULE) {
                        break 2;
                    }
                }
                if (in_array((int) $role, $optionalSlotIds, true)) {
                    $extended[] = $set;
                }
            }
            $sets = $extended;
        }

        return array_values(array_filter(
            $sets,
            static fn (array $set): bool => count($set) >= 2 && !array_diff($required, array_keys($set))
        ));
    }

    /**
     * Discount of one unit of every line in the set.
     *
     * @param array<int,string> $set
     * @param array<string,array{product_id:int,qty:float,price?:float}> $lines
     * @param array<int,array{type:string,amount:float}> $discounts
     */
    private function getSetDiscount(array $set, array $lines, array $discounts): float
    {
        $total = 0.0;
        foreach ($set as $slotId => $lineKey) {
            $price = max(0.0, (float) ($lines[$lineKey]['price'] ?? 0.0));
            $discount = $discounts[$slotId] ?? null;
            if ($discount === null) {
                continue;
            }
            if ($discount['type'] === Slot::DISCOUNT_PERCENT) {
                $total += $price * (float) $discount['amount'] / 100;
            } elseif ($discount['type'] === Slot::DISCOUNT_FIXED) {
                $total += min((float) $discount['amount'], $price);
            }
        }

        return $total;
    }
}
