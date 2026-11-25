<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Observer;

use FalconMedia\BundleDeals\Model\Rule\Condition\AbstractBundleCondition;
use FalconMedia\BundleDeals\Model\Rule\Condition\SetComplete;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Offers "Bundle deal set complete in cart" in the cart price rule conditions.
 */
class AddCartConditions implements ObserverInterface
{
    /**
     * Adds the bundle deal set condition to the cart price rule conditions.
     */
    public function execute(Observer $observer): void
    {
        $additional = $observer->getEvent()->getData('additional');
        $conditions = (array) $additional->getConditions();
        $conditions[] = [
            'label' => __('Bundle Deals'),
            'value' => [
                [
                    'value' => SetComplete::class . '|' . AbstractBundleCondition::ATTRIBUTE,
                    'label' => __('Bundle deal set complete in cart'),
                ],
            ],
        ];
        $additional->setConditions($conditions);
    }
}
