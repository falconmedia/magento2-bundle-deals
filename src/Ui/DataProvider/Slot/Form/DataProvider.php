<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\DataProvider\Slot\Form;

use FalconMedia\BundleDeals\Controller\Adminhtml\Slot\Save;
use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory;
use FalconMedia\BundleDeals\Model\Slot;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
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
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource,
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
     * @return array<int|string, array<string, mixed>>
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $slotId = (int) $this->request->getParam($this->requestFieldName);
        $data = [
            'rule_id' => (string) (int) $this->request->getParam('rule_id'),
            'sort_order' => '0',
            'slot_type' => Slot::TYPE_VARIABLE,
            'sort_by' => SortBy::POSITION,
            'use_as_entry' => '1',
            'discount_type' => Slot::DISCOUNT_NONE,
            'discount_amount' => '0',
            'exclude_special_price' => '1',
        ];
        if ($slotId) {
            $slot = $this->slotFactory->create();
            $this->slotResource->load($slot, $slotId);
            if ($slot->getId()) {
                $data = $slot->getData();
                unset($data['conditions_serialized'], $data['actions_serialized']);
            }
        }

        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (is_array($persisted) && $persisted) {
            unset($persisted['rule']);
            $data = array_merge($data, $persisted);
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);
        }

        $this->loadedData = [($slotId ?: '') => $data];

        return $this->loadedData;
    }
}
