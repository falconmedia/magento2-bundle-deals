<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Set;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule\CollectionFactory;
use FalconMedia\BundleDeals\Model\Rule;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Active rules per store for today, best priority first; cached for the request.
 */
class ActiveRuleProvider
{
    /**
     * @var array<int, Rule[]>
     */
    private array $cache = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Returns the active bundle deals of a store view by id.
     *
     * @return Rule[] keyed by rule id
     */
    public function getRules(int $storeId): array
    {
        if (!isset($this->cache[$storeId])) {
            $date = $this->timezone->scopeDate($storeId)->format('Y-m-d');
            $collection = $this->collectionFactory->create();
            $collection->addActiveFilter($storeId, $date)
                ->setOrder('priority', 'ASC')
                ->setOrder('rule_id', 'ASC');
            $rules = [];
            /**
             * @var Rule $rule
             */
            foreach ($collection as $rule) {
                $rules[(int) $rule->getId()] = $rule;
            }
            $this->cache[$storeId] = $rules;
        }

        return $this->cache[$storeId];
    }

    /**
     * Returns one active bundle deal of a store view, if any.
     */
    public function getRule(int $ruleId, int $storeId): ?Rule
    {
        return $this->getRules($storeId)[$ruleId] ?? null;
    }
}
