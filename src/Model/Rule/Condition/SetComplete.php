<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Rule\Condition;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;

/**
 * Cart condition: the cart holds a complete set of the selected bundle deal rule.
 */
class SetComplete extends AbstractBundleCondition
{
    /**
     * @inheritdoc
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([self::ATTRIBUTE => __('Bundle deal set complete in cart')]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function validate(AbstractModel $model)
    {
        $quote = $model instanceof Address ? $model->getQuote() : $model->getData('quote');
        if (!$quote instanceof Quote) {
            return false;
        }

        return $this->matches($this->cartSetResolver->isSetComplete($quote, $this->getBundleRuleId()));
    }
}
