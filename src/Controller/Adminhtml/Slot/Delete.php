<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Slot;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';

    public function __construct(
        Context $context,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $slot = $this->slotFactory->create();
        $this->slotResource->load($slot, (int) $this->getRequest()->getParam('slot_id'));
        if (!$slot->getId()) {
            $this->messageManager->addErrorMessage(__('This slot no longer exists.'));

            return $redirect->setPath('*/rule/');
        }

        $ruleId = $slot->getRuleId();
        try {
            $this->slotResource->delete($slot);
            $this->messageManager->addSuccessMessage(__('You deleted the slot.'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                (string) __('Something went wrong while deleting the slot.')
            );
        }

        return $redirect->setPath('*/rule/edit', ['rule_id' => $ruleId]);
    }
}
