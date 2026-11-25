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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';

    public function __construct(
        Context $context,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $rule = $this->ruleFactory->create();
        $this->ruleResource->load($rule, (int) $this->getRequest()->getParam('rule_id'));
        if (!$rule->getId()) {
            $this->messageManager->addErrorMessage(__('This bundle deal no longer exists.'));

            return $redirect;
        }

        try {
            $this->ruleResource->delete($rule);
            $this->messageManager->addSuccessMessage(__('You deleted the bundle deal.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                (string) __('Something went wrong while deleting the bundle deal.')
            );
        }

        return $redirect;
    }
}
