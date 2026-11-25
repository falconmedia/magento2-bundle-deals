<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Rule;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\RuleFactory;
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
        private readonly RuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $ruleId = (int) $this->getRequest()->getParam('rule_id');
        $rule = $this->ruleFactory->create();
        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This bundle deal no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $page = $this->pageFactory->create();
        $page->setActiveMenu('FalconMedia_BundleDeals::rules');
        $page->getConfig()->getTitle()->prepend((string) __('Bundle Deals'));
        $page->getConfig()->getTitle()->prepend(
            $rule->getId() ? (string) $rule->getData('name') : (string) __('New Bundle Deal')
        );

        return $page;
    }
}
