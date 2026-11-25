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
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Deactivates generated cart price rules that no slot refers to anymore once a bundle deal is deleted.
 */
class DisableOrphanedRules implements ObserverInterface
{
    public function __construct(
        private readonly SalesRuleSynchronizer $synchronizer,
        private readonly GeneratedRuleRegistry $generatedRules
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->generatedRules->reset();
        $this->synchronizer->disableOrphanedRules();
    }
}
