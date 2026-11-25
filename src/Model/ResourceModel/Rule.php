<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Rule resource; keeps the store assignment in falconmedia_bundle_deal_rule_store.
 */
class Rule extends AbstractDb
{
    public const TABLE = 'falconmedia_bundle_deal_rule';
    public const STORE_TABLE = 'falconmedia_bundle_deal_rule_store';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'rule_id');
    }

    /**
     * @inheritdoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $nullable = [
            'from_date',
            'to_date',
            'external_reference',
            'url_key',
            'description',
            'color_group',
            'color_option_id',
            'color_sort_order',
        ];
        foreach ($nullable as $field) {
            if ($object->getData($field) === '') {
                $object->setData($field, null);
            }
        }
        if ($object->hasData('customer_group_ids')) {
            $groupIds = $object->getData('customer_group_ids');
            if (is_array($groupIds)) {
                $groupIds = array_filter(array_map('strval', $groupIds), static fn (string $id): bool => $id !== '');
                $object->setData('customer_group_ids', $groupIds ? implode(',', array_map('intval', $groupIds)) : null);
            } elseif ($groupIds === '') {
                $object->setData('customer_group_ids', null);
            }
        }

        return parent::_beforeSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        if ($object->hasData('store_ids')) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::STORE_TABLE);
            $ruleId = (int) $object->getId();
            $storeIds = $object->getData('store_ids');
            $storeIds = is_array($storeIds) ? $storeIds : explode(',', (string) $storeIds);
            $storeIds = array_unique(array_map(
                'intval',
                array_filter($storeIds, static fn ($id): bool => (string) $id !== '')
            ));
            if (!$storeIds) {
                $storeIds = [0];
            }

            $connection->delete($table, ['rule_id = ?' => $ruleId]);
            $rows = [];
            foreach ($storeIds as $storeId) {
                $rows[] = ['rule_id' => $ruleId, 'store_id' => $storeId];
            }
            $connection->insertMultiple($table, $rows);
        }

        return parent::_afterSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $object->setData('store_ids', $this->getStoreIds((int) $object->getId()));
        }

        return parent::_afterLoad($object);
    }

    /**
     * Returns the ids of the store views of a bundle deal.
     *
     * @return int[]
     */
    public function getStoreIds(int $ruleId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::STORE_TABLE), ['store_id'])
            ->where('rule_id = ?', $ruleId);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Returns the id of the bundle deal with an external reference, if any.
     */
    public function getIdByExternalReference(string $reference): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['rule_id'])
            ->where('external_reference = ?', $reference);
        $ruleId = $connection->fetchOne($select);

        return $ruleId ? (int) $ruleId : null;
    }
}
