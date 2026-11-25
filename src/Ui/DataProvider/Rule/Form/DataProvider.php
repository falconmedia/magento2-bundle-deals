<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\DataProvider\Rule\Form;

use FalconMedia\BundleDeals\Controller\Adminhtml\Rule\Save;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule\CollectionFactory;
use FalconMedia\BundleDeals\Model\RuleFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string,array<string,mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Returns the form data, keyed by entity id.
     *
     * @return array<int|string,array<string,mixed>>
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $ruleId = (int) $this->request->getParam($this->requestFieldName);
        $data = ['store_ids' => ['0'], 'is_active' => '1', 'priority' => '0', 'exclude_other_discounts' => '1'];
        if ($ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, $ruleId);
            if ($rule->getId()) {
                $data = $rule->getData();
                $data['store_ids'] = array_map('strval', $rule->getStoreIds() ?: [0]);
                $data['customer_group_ids'] = array_map('strval', $rule->getCustomerGroupIds());
            }
        }

        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (is_array($persisted) && $persisted) {
            $data = array_merge($data, $persisted);
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);
        }

        $this->loadedData = [($ruleId ?: '') => $data];

        return $this->loadedData;
    }
}
