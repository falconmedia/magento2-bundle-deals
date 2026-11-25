<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Slot extends AbstractDb
{
    public const TABLE = 'falconmedia_bundle_deal_slot';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'slot_id');
    }
}
