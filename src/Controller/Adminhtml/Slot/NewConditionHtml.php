<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Slot;

use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\ConditionFactory;

/**
 * Renders a new condition row for the slot conditions tree.
 */
class NewConditionHtml extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';

    public function __construct(
        Context $context,
        private readonly RawFactory $rawFactory,
        private readonly ConditionFactory $conditionFactory,
        private readonly SlotFactory $slotFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $types = explode('|', str_replace('-', '/', (string) $this->getRequest()->getParam('type', '')));
        $jsFormObject = (string) $this->getRequest()->getParam('form', '');
        $formName = (string) $this->getRequest()->getParam('form_namespace', '');
        foreach ([$jsFormObject, $formName] as $identifier) {
            if ($identifier !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
                return $result->setContents('');
            }
        }

        try {
            $condition = $this->conditionFactory->create($types[0]);
        } catch (\InvalidArgumentException $e) {
            return $result->setContents('');
        }
        if (!$condition instanceof AbstractCondition) {
            return $result->setContents('');
        }

        $condition->setId($this->getRequest()->getParam('id'))
            ->setType($types[0])
            ->setRule($this->slotFactory->create())
            ->setPrefix('conditions');
        if (!empty($types[1])) {
            $condition->setAttribute($types[1]);
        }
        $condition->setJsFormObject($jsFormObject);
        $condition->setFormName($formName);

        return $result->setContents($condition->asHtmlRecursive());
    }
}
