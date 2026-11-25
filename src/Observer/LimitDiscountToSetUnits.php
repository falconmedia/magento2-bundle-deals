<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Observer;

use FalconMedia\BundleDeals\Model\Discount\GeneratedRuleRegistry;
use FalconMedia\BundleDeals\Model\Set\CartSetResolver;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Rule\Action\Discount\Data;

/**
 * Scales the discount of a generated bundle deal rule to the units of the line that are part of a complete set,
 * so 3 gloves with 1 pair of shin guards get the set discount once.
 */
class LimitDiscountToSetUnits implements ObserverInterface
{
    public function __construct(
        private readonly GeneratedRuleRegistry $generatedRules,
        private readonly CartSetResolver $cartSetResolver
    ) {
    }

    public function execute(Observer $observer): void
    {
        $rule = $observer->getEvent()->getData('rule');
        $item = $observer->getEvent()->getData('item');
        $result = $observer->getEvent()->getData('result');
        $qty = (float) $observer->getEvent()->getData('qty');
        if (!$rule instanceof Rule || !$item instanceof AbstractItem || !$result instanceof Data || $qty <= 0) {
            return;
        }
        $target = $this->generatedRules->get((int) $rule->getId());
        if ($target === null) {
            return;
        }

        $covered = $this->cartSetResolver->getCoveredUnits($item, $target['rule_id'], $target['slot_id']);
        if ($covered >= $qty) {
            return;
        }
        $factor = $covered / $qty;
        $result->setAmount($result->getAmount() * $factor);
        $result->setBaseAmount($result->getBaseAmount() * $factor);
        $result->setOriginalAmount($result->getOriginalAmount() * $factor);
        $result->setBaseOriginalAmount($result->getBaseOriginalAmount() * $factor);
    }
}
