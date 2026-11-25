<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\DataProvider\Rule\Listing;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Rule grid collection with the number of slots and the assigned store ids.
 */
class Collection extends SearchResult
{
    /**
     * Joins the store ids into the grid select.
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $connection = $this->getConnection();
        $this->getSelect()->columns([
            'slot_count' => new \Zend_Db_Expr(sprintf(
                '(%s)',
                $connection->select()
                    ->from(['slot' => $this->getTable(SlotResource::TABLE)], [new \Zend_Db_Expr('COUNT(*)')])
                    ->where('slot.rule_id = main_table.rule_id')
            )),
            'store_ids' => new \Zend_Db_Expr(sprintf(
                '(%s)',
                $connection->select()
                    ->from(
                        ['rule_store' => $this->getTable(RuleResource::STORE_TABLE)],
                        [new \Zend_Db_Expr('GROUP_CONCAT(rule_store.store_id)')]
                    )
                    ->where('rule_store.rule_id = main_table.rule_id')
            )),
        ]);

        return $this;
    }
}
