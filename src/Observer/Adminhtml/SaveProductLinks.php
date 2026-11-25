<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Observer\Adminhtml;

use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;
use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use FalconMedia\BundleDeals\Ui\DataProvider\Product\Form\Modifier\BundleDeals;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Stores the manual bundle deal slot links posted with the product form and reindexes the changed rules.
 */
class SaveProductLinks implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        private readonly Index $index,
        private readonly RuleIndexer $ruleIndexer,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * Saves the manual bundle deal links posted by the product form.
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        $posted = $this->request->getParam(BundleDeals::DATA_KEY);
        if (!$product instanceof Product || !$product->getId() || !is_array($posted)
            || !$this->authorization->isAllowed(BundleDeals::ACL_RESOURCE)
        ) {
            return;
        }

        $links = [];
        foreach ($posted as $fieldKey => $mode) {
            $slotValue = BundleDeals::slotValue((string) $fieldKey);
            if ($slotValue === null) {
                continue;
            }
            switch ((string) $mode) {
                case BundleDeals::MODE_INHERIT:
                    $links[$slotValue] = null;
                    break;
                case BundleDeals::MODE_ENTRY:
                    $links[$slotValue] = true;
                    break;
                case BundleDeals::MODE_NO_ENTRY:
                    $links[$slotValue] = false;
                    break;
            }
        }

        try {
            foreach ($this->index->replaceProductLinks((int) $product->getId(), $links) as $ruleId) {
                $this->ruleIndexer->reindexRule((int) $ruleId);
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                (string) __('The bundle deal links of this product could not be saved.')
            );
        }
    }
}
