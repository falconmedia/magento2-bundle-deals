<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Discount;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule;

/**
 * Lists extra automatic cart price rules (no coupon) that reference a bundle deal as a whole.
 *
 * The per slot discounts of the bundle deal itself are not included; the storefront reads those from the slots.
 *
 * The storefront uses these to show the expected set price; the cart totals stay the source of truth.
 */
class DiscountEstimator
{
    public const TYPE_PERCENT = 'by_percent';
    public const TYPE_FIXED_PER_ITEM = 'by_fixed';
    public const TYPE_FIXED_CART = 'cart_fixed';

    /**
     * @var array<string, array<int, array{type: string, amount: float, stop: bool}>>
     */
    private array $cache = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConditionReferenceFinder $referenceFinder,
        private readonly Json $json,
        private readonly TimezoneInterface $timezone,
        private readonly GeneratedRuleRegistry $generatedRules
    ) {
    }

    /**
     * Discounts in the order Magento applies them, up to the first rule that stops further processing.
     *
     * @return array<int, array{type: string, amount: float, stop: bool}>
     */
    public function getDiscounts(int $bundleRuleId, int $websiteId, int $customerGroupId): array
    {
        $key = $bundleRuleId . '|' . $websiteId . '|' . $customerGroupId;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $collection = $this->collectionFactory->create();
        $collection->addWebsiteGroupDateFilter(
            $websiteId,
            $customerGroupId,
            $this->timezone->date()->format('Y-m-d')
        );
        $collection->addFieldToFilter('coupon_type', ['eq' => Rule::COUPON_TYPE_NO_COUPON]);
        $generatedIds = $this->generatedRules->getSalesRuleIds();
        if ($generatedIds) {
            $collection->addFieldToFilter('rule_id', ['nin' => $generatedIds]);
        }
        $collection->addFieldToFilter('simple_action', [
            'in' => [self::TYPE_PERCENT, self::TYPE_FIXED_PER_ITEM, self::TYPE_FIXED_CART],
        ]);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('rule_id', 'ASC');

        $discounts = [];
        /**
         * @var Rule $rule
         */
        foreach ($collection as $rule) {
            $appliesToBundleItems = $rule->getData('applies_to_bundle_items');
            if ($appliesToBundleItems !== null && !(bool) $appliesToBundleItems) {
                continue;
            }
            if (!in_array($bundleRuleId, $this->getReferencedRuleIds($rule), true)) {
                continue;
            }
            $stop = (bool) $rule->getStopRulesProcessing();
            $discounts[] = [
                'type' => (string) $rule->getSimpleAction(),
                'amount' => (float) $rule->getDiscountAmount(),
                'stop' => $stop,
            ];
            if ($stop) {
                break;
            }
        }

        return $this->cache[$key] = $discounts;
    }

    /**
     * Returns the bundle deal ids that a cart price rule refers to.
     *
     * @return int[]
     */
    private function getReferencedRuleIds(Rule $rule): array
    {
        $ids = [];
        foreach (['conditions_serialized', 'actions_serialized'] as $field) {
            $serialized = (string) $rule->getData($field);
            if ($serialized === '' || !str_contains($serialized, 'FalconMedia')) {
                continue;
            }
            try {
                $tree = $this->json->unserialize($serialized);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $ids[] = $this->referenceFinder->findRequiredRuleIds(is_array($tree) ? $tree : null);
        }

        return array_values(array_unique(array_merge([], ...$ids)));
    }
}
