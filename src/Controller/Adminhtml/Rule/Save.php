<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Adminhtml\Rule;

use FalconMedia\BundleDeals\Model\Page\UrlKeyGenerator;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\RuleFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'FalconMedia_BundleDeals::rules';
    public const PERSISTOR_KEY = 'falconmedia_bundle_deal_rule';

    private const FIELDS = [
        'name',
        'title',
        'is_active',
        'store_ids',
        'from_date',
        'to_date',
        'priority',
        'exclude_other_discounts',
        'customer_group_ids',
        'url_key',
        'description',
        'color_group',
        'color_option_id',
        'color_sort_order',
    ];

    public function __construct(
        Context $context,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly DateFilter $dateFilter,
        private readonly UrlKeyGenerator $urlKeyGenerator,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $post = (array) $this->getRequest()->getPostValue();
        if (!$post) {
            return $redirect->setPath('*/*/');
        }

        $ruleId = (int) ($post['rule_id'] ?? 0);
        $rule = $this->ruleFactory->create();
        try {
            if ($ruleId) {
                $this->ruleResource->load($rule, $ruleId);
                if (!$rule->getId()) {
                    throw new LocalizedException(__('This bundle deal no longer exists.'));
                }
            }

            $data = array_intersect_key($post, array_flip(self::FIELDS));
            $data['name'] = trim((string) ($data['name'] ?? ''));
            if ($data['name'] === '') {
                throw new LocalizedException(__('Please enter a name.'));
            }
            foreach (['from_date', 'to_date'] as $field) {
                $data[$field] = empty($data[$field]) ? null : $this->dateFilter->filter((string) $data[$field]);
            }
            if ($data['from_date'] && $data['to_date'] && $data['to_date'] < $data['from_date']) {
                throw new LocalizedException(__('The end date cannot be before the start date.'));
            }
            $data['is_active'] = (int) ($data['is_active'] ?? 0);
            $data['exclude_other_discounts'] = (int) ($data['exclude_other_discounts'] ?? 0);
            $data['priority'] = (int) ($data['priority'] ?? 0);
            $data['store_ids'] = array_values((array) ($data['store_ids'] ?? [0]));
            $data['customer_group_ids'] = array_values(array_filter(
                array_map('strval', (array) ($data['customer_group_ids'] ?? [])),
                static fn (string $id): bool => $id !== ''
            ));
            $urlKey = $this->urlKeyGenerator->normalize((string) ($data['url_key'] ?? ''));
            if ($urlKey === '') {
                $urlKey = $this->urlKeyGenerator->generate(
                    (string) (trim((string) ($data['title'] ?? '')) ?: $data['name']),
                    $ruleId
                );
            } elseif ($this->urlKeyGenerator->isTaken($urlKey, $ruleId)) {
                throw new LocalizedException(__('The URL key "%1" is already used by another bundle deal.', $urlKey));
            }
            $data['url_key'] = $urlKey;
            $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;
            $data['color_group'] = trim((string) ($data['color_group'] ?? '')) ?: null;
            $data['color_option_id'] = (int) ($data['color_option_id'] ?? 0) ?: null;
            $colorSortOrder = trim((string) ($data['color_sort_order'] ?? ''));
            $data['color_sort_order'] = $colorSortOrder === '' ? null : (int) $colorSortOrder;

            $rule->addData($data);
            $this->ruleResource->save($rule);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('You saved the bundle deal.'));

            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['rule_id' => $rule->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                (string) __('Something went wrong while saving the bundle deal.')
            );
        }

        $this->dataPersistor->set(
            self::PERSISTOR_KEY,
            array_intersect_key($post, array_flip([...self::FIELDS, 'rule_id']))
        );

        return $ruleId
            ? $redirect->setPath('*/*/edit', ['rule_id' => $ruleId])
            : $redirect->setPath('*/*/new');
    }
}
