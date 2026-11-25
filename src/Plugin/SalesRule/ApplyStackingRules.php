<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Plugin\SalesRule;

use FalconMedia\BundleDeals\Model\Discount\GeneratedRuleRegistry;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use FalconMedia\BundleDeals\Model\Set\CartSetResolver;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RulesApplier;

/**
 * Keeps other cart price rules off items in a complete set when the bundle deal excludes other discounts,
 * or when the cart price rule is not valid on bundle deal items.
 */
class ApplyStackingRules
{
    public function __construct(
        private readonly CartSetResolver $cartSetResolver,
        private readonly ActiveRuleProvider $activeRuleProvider,
        private readonly GeneratedRuleRegistry $generatedRules
    ) {
    }

    /**
     * Drops the cart price rules that must not apply to items in a complete set.
     *
     * @param AbstractItem $item
     * @param iterable<Rule> $rules
     * @param bool $skipValidation
     * @param string[] $couponCodes
     * @return array<int, mixed>|null
     */
    public function beforeApplyRules(
        RulesApplier $subject,
        $item,
        $rules,
        $skipValidation,
        array $couponCodes = []
    ): ?array {
        if (!$item instanceof AbstractItem) {
            return null;
        }
        $bundleRuleIds = $this->cartSetResolver->getRuleIdsForItem($item);
        if (!$bundleRuleIds) {
            return null;
        }

        $exclusive = false;
        $storeId = (int) $item->getQuote()->getStoreId();
        foreach ($bundleRuleIds as $bundleRuleId) {
            if ($this->activeRuleProvider->getRule($bundleRuleId, $storeId)?->isExclusive()) {
                $exclusive = true;
                break;
            }
        }

        $filtered = [];
        foreach ($rules as $key => $rule) {
            if ($this->generatedRules->get((int) $rule->getId()) !== null) {
                $filtered[$key] = $rule;
                continue;
            }
            if ($exclusive || !$this->appliesToBundleItems($rule)) {
                continue;
            }
            $filtered[$key] = $rule;
        }

        return [$item, $filtered, $skipValidation, $couponCodes];
    }

    private function appliesToBundleItems(Rule $rule): bool
    {
        $value = $rule->getData('applies_to_bundle_items');

        return $value === null || (bool) $value;
    }
}
