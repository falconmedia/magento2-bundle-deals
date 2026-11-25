<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel\Rule;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\Rule;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'rule_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Rule::class, RuleResource::class);
    }

    /**
     * Limits to rules that are active for the store on the given date (Y-m-d).
     */
    public function addActiveFilter(int $storeId, string $date): self
    {
        $this->addFieldToFilter('main_table.is_active', ['eq' => 1]);
        $this->getSelect()
            ->where('main_table.from_date IS NULL OR main_table.from_date <= ?', $date)
            ->where('main_table.to_date IS NULL OR main_table.to_date >= ?', $date)
            ->where(
                'EXISTS (?)',
                $this->getConnection()->select()
                    ->from(['rs' => $this->getTable(RuleResource::STORE_TABLE)], [new \Zend_Db_Expr('1')])
                    ->where('rs.rule_id = main_table.rule_id')
                    ->where('rs.store_id IN (?)', [0, $storeId])
            );

        return $this;
    }
}
