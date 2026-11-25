<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Set;

use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * Tells which cart lines belong to a complete bundle set, whether they were added as a set or one by one.
 */
class CartSetResolver
{
    /**
     * @var array<string, array<int, array<string, array<int, int>>>>
     */
    private array $cache = [];

    /**
     * @var array<int, array<int, int>> rule id => slot ids (as keys) without discount on special price items
     */
    private array $specialPriceExcluded = [];

    public function __construct(
        private readonly Index $index,
        private readonly ActiveRuleProvider $activeRuleProvider,
        private readonly SetAllocator $setAllocator
    ) {
    }

    public function isSetComplete(Quote $quote, int $ruleId): bool
    {
        return !empty($this->resolve($quote)[$ruleId]);
    }

    /**
     * Tells whether the cart line is part of a complete set of the bundle deal.
     *
     * @param int|null $slotId only count units that fill this slot
     */
    public function isItemInCompleteSet(AbstractItem $item, int $ruleId, ?int $slotId = null): bool
    {
        return $this->getCoveredUnits($item, $ruleId, $slotId) > 0;
    }

    /**
     * Units of the cart line that are part of complete sets of the rule.
     *
     * @param int|null $slotId only count units that fill this slot
     */
    public function getCoveredUnits(AbstractItem $item, int $ruleId, ?int $slotId = null): int
    {
        $lineItem = $item->getParentItem() ?: $item;
        $quote = $lineItem->getQuote();
        if (!$quote instanceof Quote) {
            return 0;
        }
        $slots = $this->resolve($quote)[$ruleId][$this->getLineKey($lineItem)] ?? [];

        return $slotId === null ? (int) array_sum($slots) : (int) ($slots[$slotId] ?? 0);
    }

    /**
     * Tells whether the slot gives no set discount to items that already have a special price.
     */
    public function isSpecialPriceExcluded(int $ruleId, int $slotId): bool
    {
        if (!isset($this->specialPriceExcluded[$ruleId])) {
            $this->specialPriceExcluded[$ruleId] = array_flip($this->index->getSpecialPriceExcludedSlotIds([$ruleId]));
        }

        return isset($this->specialPriceExcluded[$ruleId][$slotId]);
    }

    /**
     * Rule ids for which the cart line is part of a complete set.
     *
     * @return int[]
     */
    public function getRuleIdsForItem(AbstractItem $item): array
    {
        $lineItem = $item->getParentItem() ?: $item;
        $quote = $lineItem->getQuote();
        if (!$quote instanceof Quote) {
            return [];
        }
        $lineKey = $this->getLineKey($lineItem);
        $ruleIds = [];
        foreach ($this->resolve($quote) as $ruleId => $lines) {
            if (isset($lines[$lineKey])) {
                $ruleIds[] = $ruleId;
            }
        }

        return $ruleIds;
    }

    /**
     * Resolves the covered units per bundle deal, cart line and slot.
     *
     * @return array<int, array<string, array<int, int>>> rule id => line key => slot id => covered units
     */
    public function resolve(Quote $quote): array
    {
        $lines = [];
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItem() || $item->isDeleted() || (float) $item->getQty() <= 0) {
                continue;
            }
            $lines[$this->getLineKey($item)] = [
                'product_id' => (int) $item->getProductId(),
                'qty' => (float) $item->getQty(),
                'price' => (float) $item->getPrice(),
            ];
        }

        $cacheKey = spl_object_id($quote) . '|' . (int) $quote->getStoreId() . '|' . json_encode($lines);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $rules = $this->activeRuleProvider->getRules((int) $quote->getStoreId());
        $productIds = array_column($lines, 'product_id');
        $membership = $productIds ? $this->index->getMembershipForProducts($productIds) : [];
        $membership = array_intersect_key($membership, $rules);
        $slotsByRule = $this->index->getSlotIdsByRule(array_keys($membership));

        $rolesByRule = [];
        foreach ($membership as $ruleId => $productsBySlot) {
            foreach ($slotsByRule[$ruleId] ?? [] as $slotId) {
                $rolesByRule[$ruleId][$slotId] = $productsBySlot[$slotId] ?? [];
            }
        }
        $result = $rolesByRule
            ? $this->setAllocator->allocate(
                $rolesByRule,
                $lines,
                $this->index->getSlotDiscounts(array_keys($rolesByRule)),
                $this->index->getOptionalSlotIds(array_keys($rolesByRule))
            )
            : [];

        $this->cache = [$cacheKey => $result];

        return $result;
    }

    private function getLineKey(AbstractItem $item): string
    {
        return $item->getId() ? 'id' . $item->getId() : 'obj' . spl_object_id($item);
    }
}
