<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel\Slot;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'slot_id';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Slot::class, SlotResource::class);
    }

    /**
     * Limits the collection to the slots of a bundle deal.
     */
    public function addRuleFilter(int $ruleId): self
    {
        $this->addFieldToFilter('rule_id', ['eq' => $ruleId]);
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);
        $this->setOrder('slot_id', self::SORT_ORDER_ASC);

        return $this;
    }
}
