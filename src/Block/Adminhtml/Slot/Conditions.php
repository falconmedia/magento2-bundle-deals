<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Adminhtml\Slot;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\Slot;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Rule\Block\Conditions as ConditionsRenderer;
use Magento\Rule\Model\Condition\AbstractCondition;

/**
 * Catalog rule style conditions tree for the slot form.
 */
class Conditions extends Generic
{
    public const FORM_NAME = 'falconmedia_bundledeals_slot_form';

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        private readonly ConditionsRenderer $conditionsRenderer,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource,
        array $data = []
    ) {
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _prepareForm()
    {
        $slot = $this->getSlot();
        $fieldsetId = self::FORM_NAME . 'rule_conditions_fieldset_' . (int) $slot->getId();

        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('rule_');

        $renderer = $this->getLayout()->createBlock(Fieldset::class);
        $renderer->setTemplate('Magento_CatalogRule::promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl(
                'bundledeals/slot/newConditionHtml/form/' . $fieldsetId,
                ['form_namespace' => self::FORM_NAME]
            ))
            ->setFieldSetId($fieldsetId);

        $fieldset = $form->addFieldset(
            'conditions_fieldset',
            ['legend' => __('Products that can fill this slot')]
        )->setRenderer($renderer);

        $fieldset->addField(
            'conditions',
            'text',
            [
                'name' => 'conditions',
                'label' => __('Conditions'),
                'title' => __('Conditions'),
                'required' => true,
                'data-form-part' => self::FORM_NAME,
            ]
        )->setRule($slot)->setRenderer($this->conditionsRenderer);

        $form->setValues($slot->getData());
        $this->prepareConditions($slot->getConditions(), $fieldsetId);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    private function getSlot(): Slot
    {
        $slot = $this->slotFactory->create();
        $slotId = (int) $this->getRequest()->getParam('slot_id');
        if ($slotId) {
            $this->slotResource->load($slot, $slotId);
        }

        return $slot;
    }

    private function prepareConditions(AbstractCondition $condition, string $fieldsetId): void
    {
        $condition->setFormName(self::FORM_NAME);
        $condition->setJsFormObject($fieldsetId);
        foreach ((array) $condition->getConditions() as $child) {
            if ($child instanceof AbstractCondition) {
                $this->prepareConditions($child, $fieldsetId);
            }
        }
    }
}
