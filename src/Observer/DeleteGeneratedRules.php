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
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Removes the generated cart price rules before a bundle deal or slot is deleted.
 */
class DeleteGeneratedRules implements ObserverInterface
{
    public function __construct(
        private readonly SalesRuleSynchronizer $synchronizer,
        private readonly GeneratedRuleRegistry $generatedRules
    ) {
    }

    /**
     * Deletes the generated cart price rules of a deleted bundle deal or slot.
     */
    public function execute(Observer $observer): void
    {
        $object = $observer->getEvent()->getData('object');
        if ($object instanceof Rule) {
            $this->synchronizer->deleteForRule((int) $object->getId());
        } elseif ($object instanceof Slot) {
            $this->synchronizer->deleteForSlot($object);
        }
        $this->generatedRules->reset();
    }
}
