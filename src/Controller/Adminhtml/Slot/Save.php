<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Slot;

use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\Slot;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';
    public const PERSISTOR_KEY = 'falconmedia_bundle_deal_slot';

    /**
     * Form fields kept in the data persistor after a failed save (no form_key or condition tree).
     */
    private const FORM_FIELDS = [
        'slot_id',
        'rule_id',
        'title',
        'sort_order',
        'slot_type',
        'sort_by',
        'use_as_entry',
        'discount_type',
        'discount_amount',
        'exclude_special_price',
    ];

    public function __construct(
        Context $context,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource,
        private readonly SortBy $sortBy,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $post = (array) $this->getRequest()->getPostValue();
        if (!$post) {
            return $redirect->setPath('*/rule/');
        }

        $slotId = (int) ($post['slot_id'] ?? 0);
        $ruleId = (int) ($post['rule_id'] ?? 0);
        $slot = $this->slotFactory->create();
        try {
            if ($slotId) {
                $this->slotResource->load($slot, $slotId);
                if (!$slot->getId()) {
                    throw new LocalizedException(__('This slot no longer exists.'));
                }
                $ruleId = $slot->getRuleId();
            }
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);
            if (!$rule->getId()) {
                $this->messageManager->addErrorMessage(__('This bundle deal no longer exists.'));

                return $redirect->setPath('*/rule/');
            }

            $slot->addData($this->prepareData($post));
            $slot->setData('rule_id', $ruleId);
            if (isset($post['rule']['conditions']) && is_array($post['rule']['conditions'])) {
                $slot->loadPost(['conditions' => $post['rule']['conditions']]);
            }
            $this->slotResource->save($slot);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('You saved the slot.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['slot_id' => $slot->getId()]);
            }

            return $redirect->setPath('*/rule/edit', ['rule_id' => $ruleId]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, (string) __('Something went wrong while saving the slot.'));
        }

        $this->dataPersistor->set(self::PERSISTOR_KEY, array_intersect_key($post, array_flip(self::FORM_FIELDS)));

        return $slotId
            ? $redirect->setPath('*/*/edit', ['slot_id' => $slotId])
            : $redirect->setPath('*/*/new', ['rule_id' => $ruleId]);
    }

    /**
     * Validates the posted slot data and returns the values to save.
     *
     * @param array<string,mixed> $post
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function prepareData(array $post): array
    {
        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            throw new LocalizedException(__('Please enter a title.'));
        }

        $sortBy = (string) ($post['sort_by'] ?? SortBy::POSITION);
        if (!in_array($sortBy, array_column($this->sortBy->toOptionArray(), 'value'), true)) {
            $sortBy = SortBy::POSITION;
        }

        $slotType = (string) ($post['slot_type'] ?? Slot::TYPE_VARIABLE);
        if (!in_array($slotType, [Slot::TYPE_FIXED, Slot::TYPE_VARIABLE, Slot::TYPE_OPTIONAL], true)) {
            $slotType = Slot::TYPE_VARIABLE;
        }

        $discountType = (string) ($post['discount_type'] ?? Slot::DISCOUNT_NONE);
        if (!in_array($discountType, [Slot::DISCOUNT_NONE, Slot::DISCOUNT_PERCENT, Slot::DISCOUNT_FIXED], true)) {
            $discountType = Slot::DISCOUNT_NONE;
        }
        $amount = $discountType === Slot::DISCOUNT_NONE ? 0.0 : (float) ($post['discount_amount'] ?? 0);
        if ($amount < 0) {
            throw new LocalizedException(__('The discount amount cannot be negative.'));
        }
        if ($discountType === Slot::DISCOUNT_PERCENT && $amount > 100) {
            throw new LocalizedException(__('A percentage discount cannot be more than 100.'));
        }

        return [
            'title' => $title,
            'sort_order' => (int) ($post['sort_order'] ?? 0),
            'slot_type' => $slotType,
            'sort_by' => $sortBy,
            'use_as_entry' => (int) ($post['use_as_entry'] ?? 0),
            'discount_type' => $discountType,
            'discount_amount' => $amount,
            'exclude_special_price' => (int) ($post['exclude_special_price'] ?? 0),
        ];
    }
}
