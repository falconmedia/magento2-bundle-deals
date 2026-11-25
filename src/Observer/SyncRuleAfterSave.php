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
use FalconMedia\BundleDeals\Model\Discount\SalesRuleSynchronizer;
use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * After a bundle deal or one of its slots is saved: refresh the generated cart price rules and the product index,
 * and deactivate generated cart price rules that no slot refers to anymore.
 */
class SyncRuleAfterSave implements ObserverInterface
{
    public function __construct(
        private readonly SalesRuleSynchronizer $synchronizer,
        private readonly GeneratedRuleRegistry $generatedRules,
        private readonly RuleIndexer $ruleIndexer,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource
    ) {
    }

    public function execute(Observer $observer): void
    {
        $object = $observer->getEvent()->getData('object');
        if ($object instanceof Rule) {
            $this->synchronizer->syncRule($object);
            $this->ruleIndexer->reindexRuleModel($object);
        } elseif ($object instanceof Slot) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $object->getRuleId());
            if (!$rule->getId()) {
                return;
            }
            if (!$object->isDeleted()) {
                $this->synchronizer->syncSlot($rule, $object);
            }
            $this->ruleIndexer->reindexRuleModel($rule);
        }
        $this->generatedRules->reset();
        $this->synchronizer->disableOrphanedRules();
    }
}
