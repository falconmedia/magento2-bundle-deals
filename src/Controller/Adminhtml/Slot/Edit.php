<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Slot;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $slotId = (int) $this->getRequest()->getParam('slot_id');
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        $slot = $this->slotFactory->create();
        if ($slotId) {
            $this->slotResource->load($slot, $slotId);
            if (!$slot->getId()) {
                $this->messageManager->addErrorMessage(__('This slot no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/rule/');
            }
            $ruleId = $slot->getRuleId();
        }

        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, $ruleId);
        if (!$rule->getId()) {
            $this->messageManager->addErrorMessage(__('This bundle deal no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/rule/');
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('FalconMedia_BundleDeals::rules');
        $page->getConfig()->getTitle()->prepend((string) __('Bundle Deals'));
        $page->getConfig()->getTitle()->prepend(
            $slot->getId()
                ? (string) __('%1: %2', (string) $rule->getData('name'), (string) $slot->getData('title'))
                : (string) __('%1: New Slot', (string) $rule->getData('name'))
        );

        return $page;
    }
}
