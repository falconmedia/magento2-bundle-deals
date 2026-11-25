<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Discount;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory as SlotCollectionFactory;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Rule\Condition\AbstractBundleCondition;
use FalconMedia\BundleDeals\Model\Rule\Condition\ItemInSet;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\SalesRule\Model\ResourceModel\Rule as SalesRuleResource;
use Magento\SalesRule\Model\Rule as SalesRule;
use Magento\SalesRule\Model\Rule\Condition\Combine;
use Magento\SalesRule\Model\Rule\Condition\Product\Combine as ProductCombine;
use Magento\SalesRule\Model\RuleFactory as SalesRuleFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Keeps one native cart price rule per discounted slot, so the set discount runs through the normal totals,
 * checkout, order and invoice flow.
 */
class SalesRuleSynchronizer
{
    public const NAME_PREFIX = '[Bundle deal #';
    public const DESCRIPTION = 'Managed by FalconMedia_BundleDeals. Edit the bundle deal instead of this rule.';

    public function __construct(
        private readonly SlotCollectionFactory $slotCollectionFactory,
        private readonly SlotResource $slotResource,
        private readonly SalesRuleFactory $salesRuleFactory,
        private readonly SalesRuleResource $salesRuleResource,
        private readonly GroupCollectionFactory $groupCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json
    ) {
    }

    public function syncRule(Rule $rule): void
    {
        $slots = $this->slotCollectionFactory->create()->addRuleFilter((int) $rule->getId());
        /**
         * @var Slot $slot
         */
        foreach ($slots as $slot) {
            $this->syncSlot($rule, $slot);
        }
    }

    /**
     * Creates, updates or removes the generated cart price rule of a slot.
     */
    public function syncSlot(Rule $rule, Slot $slot): void
    {
        $salesRuleId = $slot->getSalesRuleId();
        if ($slot->getDiscountType() === Slot::DISCOUNT_NONE) {
            if ($salesRuleId) {
                $this->deleteSalesRule($salesRuleId);
                $this->setSlotSalesRuleId($slot, null);
            }
            return;
        }

        $salesRule = $this->salesRuleFactory->create();
        if ($salesRuleId) {
            $this->salesRuleResource->load($salesRule, $salesRuleId);
        }

        $title = (string) ($rule->getTitle() ?: $rule->getName());
        $salesRule->addData([
            'name' => sprintf(
                '%s%d] %s / %s',
                self::NAME_PREFIX,
                (int) $rule->getId(),
                $rule->getName(),
                $slot->getTitle()
            ),
            'description' => self::DESCRIPTION,
            'is_active' => $rule->isActive() ? 1 : 0,
            'from_date' => $rule->getFromDate() ?: null,
            'to_date' => $rule->getToDate() ?: null,
            'website_ids' => $this->getWebsiteIds($rule),
            'customer_group_ids' => $this->getCustomerGroupIds($rule),
            'coupon_type' => SalesRule::COUPON_TYPE_NO_COUPON,
            'uses_per_customer' => 0,
            'sort_order' => 0,
            'stop_rules_processing' => 0,
            'is_rss' => 0,
            'apply_to_shipping' => 0,
            'simple_free_shipping' => 0,
            'applies_to_bundle_items' => 1,
            'simple_action' => $slot->getDiscountType() === Slot::DISCOUNT_PERCENT
                ? SalesRule::BY_PERCENT_ACTION
                : SalesRule::BY_FIXED_ACTION,
            'discount_amount' => $slot->getDiscountAmount(),
            'discount_qty' => null,
            'discount_step' => 0,
            'store_labels' => [0 => $title],
            'conditions_serialized' => $this->json->serialize([
                'type' => Combine::class,
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all',
            ]),
            'actions_serialized' => $this->json->serialize([
                'type' => ProductCombine::class,
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all',
                'conditions' => [[
                    'type' => ItemInSet::class,
                    'attribute' => AbstractBundleCondition::ATTRIBUTE,
                    'operator' => '==',
                    'value' => (int) $rule->getId() . ':' . (int) $slot->getId(),
                    'is_value_processed' => false,
                ]],
            ]),
        ]);
        $salesRule->unsetData('conditions');
        $salesRule->unsetData('actions');
        $this->salesRuleResource->save($salesRule);

        if ((int) $salesRule->getId() !== $salesRuleId) {
            $this->setSlotSalesRuleId($slot, (int) $salesRule->getId());
        }
    }

    /**
     * Removes the generated cart price rules of a bundle deal that is about to be deleted.
     */
    public function deleteForRule(int $ruleId): void
    {
        $slots = $this->slotCollectionFactory->create()->addRuleFilter($ruleId);
        /**
         * @var Slot $slot
         */
        foreach ($slots as $slot) {
            $this->deleteForSlot($slot);
        }
    }

    /**
     * Removes the generated cart price rule of a slot.
     */
    public function deleteForSlot(Slot $slot): void
    {
        if ($slot->getSalesRuleId()) {
            $this->deleteSalesRule($slot->getSalesRuleId());
        }
    }

    /**
     * Generated cart price rule ids.
     *
     * @return array<int,array{rule_id:int, slot_id:int}> sales rule id => bundle rule and slot
     */
    public function getGeneratedRuleMap(): array
    {
        $connection = $this->slotResource->getConnection();
        $select = $connection->select()
            ->from($this->slotResource->getMainTable(), ['sales_rule_id', 'rule_id', 'slot_id'])
            ->where('sales_rule_id IS NOT NULL');

        $map = [];
        foreach ($connection->fetchAll($select) as $row) {
            $map[(int) $row['sales_rule_id']] = [
                'rule_id' => (int) $row['rule_id'],
                'slot_id' => (int) $row['slot_id'],
            ];
        }

        return $map;
    }

    /**
     * Active generated cart price rules that no slot refers to anymore.
     *
     * @return int[]
     */
    public function getOrphanedRuleIds(): array
    {
        $connection = $this->salesRuleResource->getConnection();
        $select = $connection->select()
            ->from($this->salesRuleResource->getMainTable(), ['rule_id'])
            ->where('is_active = ?', 1)
            ->where(
                $connection->quoteInto('name LIKE ?', self::NAME_PREFIX . '%')
                . ' OR '
                . $connection->quoteInto('description = ?', self::DESCRIPTION)
            );
        $generated = array_keys($this->getGeneratedRuleMap());
        if ($generated) {
            $select->where('rule_id NOT IN (?)', $generated);
        }

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Deactivates the generated cart price rules that no slot refers to anymore.
     *
     * @return int[] the deactivated cart price rule ids
     */
    public function disableOrphanedRules(): array
    {
        $ruleIds = $this->getOrphanedRuleIds();
        if ($ruleIds) {
            $this->salesRuleResource->getConnection()->update(
                $this->salesRuleResource->getMainTable(),
                ['is_active' => 0],
                ['rule_id IN (?)' => $ruleIds]
            );
        }

        return $ruleIds;
    }

    /**
     * Deletes a cart price rule by id.
     */
    private function deleteSalesRule(int $salesRuleId): void
    {
        $salesRule = $this->salesRuleFactory->create();
        $this->salesRuleResource->load($salesRule, $salesRuleId);
        if ($salesRule->getId()) {
            $this->salesRuleResource->delete($salesRule);
        }
    }

    /**
     * Stores the generated cart price rule id on the slot.
     */
    private function setSlotSalesRuleId(Slot $slot, ?int $salesRuleId): void
    {
        $this->slotResource->getConnection()->update(
            $this->slotResource->getMainTable(),
            ['sales_rule_id' => $salesRuleId],
            ['slot_id = ?' => (int) $slot->getId()]
        );
        $slot->setData('sales_rule_id', $salesRuleId);
    }

    /**
     * Returns the website ids of the store views of a bundle deal.
     *
     * @return int[]
     */
    private function getWebsiteIds(Rule $rule): array
    {
        $storeIds = $rule->getStoreIds();
        $websiteIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$storeIds || in_array(0, $storeIds, true) || in_array((int) $store->getId(), $storeIds, true)) {
                $websiteIds[] = (int) $store->getWebsiteId();
            }
        }

        return array_values(array_unique($websiteIds));
    }

    /**
     * Returns the customer groups of the bundle deal that still exist, or all groups when it has none set.
     *
     * @return int[]
     */
    private function getCustomerGroupIds(Rule $rule): array
    {
        $allIds = array_map('intval', $this->groupCollectionFactory->create()->getAllIds());
        $ruleIds = $rule->getCustomerGroupIds();

        return $ruleIds ? array_values(array_intersect($allIds, $ruleIds)) : $allIds;
    }
}
