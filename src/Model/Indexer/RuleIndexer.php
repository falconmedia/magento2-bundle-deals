<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Indexer;

use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory as SlotCollectionFactory;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Writes the member products of every slot into falconmedia_bundle_deal_index.
 */
class RuleIndexer
{
    public function __construct(
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly SlotCollectionFactory $slotCollectionFactory,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly ProductMatcher $productMatcher,
        private readonly CandidateSorter $candidateSorter,
        private readonly Index $index,
        private readonly ManagerInterface $eventManager,
        private readonly TimezoneInterface $localeDate
    ) {
    }

    /**
     * Reindexes every bundle deal; only rules whose storefront output can have changed flush their pages.
     *
     * @return int number of rules indexed
     */
    public function reindexAll(): int
    {
        $count = 0;
        foreach ($this->ruleCollectionFactory->create() as $rule) {
            /**
             * @var Rule $rule
             */
            $this->reindexRuleModel($rule, $this->isDateBoundary($rule));
            $count++;
        }

        return $count;
    }

    /**
     * Reindexes one bundle deal by id.
     */
    public function reindexRule(int $ruleId): void
    {
        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, $ruleId);
        if ($rule->getId()) {
            $this->reindexRuleModel($rule, true);
        }
    }

    /**
     * Reindexes one bundle deal and flushes its pages when the index changed or $forceClean is set.
     */
    public function reindexRuleModel(Rule $rule, bool $forceClean = false): void
    {
        $ruleId = (int) $rule->getId();
        $manualLinks = $this->index->getManualLinks($ruleId);

        $rowsBySlot = [];
        $slots = $this->slotCollectionFactory->create()->addRuleFilter($ruleId);
        /**
         * @var Slot $slot
         */
        foreach ($slots as $slot) {
            $slotId = (int) $slot->getId();
            $manual = $manualLinks[$slotId] ?? [];
            $products = $this->productMatcher->match($slot, array_keys($manual));
            $positions = $this->candidateSorter->sort(
                $products,
                (string) $slot->getSortBy(),
                array_map(static fn (array $link): int => $link['position'], $manual)
            );
            foreach ($positions as $productId => $position) {
                $override = $manual[$productId]['use_as_entry'] ?? null;
                $rowsBySlot[$slotId][$productId] = [
                    'position' => (int) $position,
                    'is_entry' => (bool) ($override ?? $slot->isEntry()),
                ];
            }
        }

        $changed = $this->index->getRuleRows($ruleId) != $rowsBySlot;
        if ($changed) {
            $this->index->replaceRule($ruleId, $rowsBySlot);
        }
        if ($changed || $forceClean) {
            // Flushes full page cache entries of the pages that render this rule.
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $rule]);
        }
    }

    /**
     * Tells whether the rule starts today or ended yesterday, the days its visibility flips without an index change.
     */
    private function isDateBoundary(Rule $rule): bool
    {
        $today = $this->localeDate->date()->format('Y-m-d');
        $yesterday = $this->localeDate->date()->modify('-1 day')->format('Y-m-d');

        return substr((string) $rule->getFromDate(), 0, 10) === $today
            || substr((string) $rule->getToDate(), 0, 10) === $yesterday;
    }
}
