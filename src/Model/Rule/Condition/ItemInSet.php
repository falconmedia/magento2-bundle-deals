<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Rule\Condition;

use FalconMedia\BundleDeals\Model\Config\Source\Rules;
use FalconMedia\BundleDeals\Model\Config\Source\Slots;
use FalconMedia\BundleDeals\Model\Set\CartSetResolver;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Rule\Model\Condition\Context;

/**
 * Cart item condition: the item is part of a complete set of the selected bundle deal, optionally in one slot.
 *
 * Used in the "Apply the rule only to cart items matching" actions, so the discount lands on the set items only.
 */
class ItemInSet extends AbstractBundleCondition
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        CartSetResolver $cartSetResolver,
        Rules $rules,
        private readonly Slots $slots,
        array $data = []
    ) {
        parent::__construct($context, $cartSetResolver, $rules, $data);
    }

    /**
     * @inheritdoc
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([self::ATTRIBUTE => __('Item belongs to complete bundle deal set')]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function validate(AbstractModel $model)
    {
        if (!$model instanceof AbstractItem) {
            return false;
        }

        $ruleId = $this->getBundleRuleId();
        $slotId = $this->getBundleSlotId();
        $inSet = $this->cartSetResolver->isItemInCompleteSet($model, $ruleId, $slotId);
        if ($inSet && $slotId && $this->cartSetResolver->isSpecialPriceExcluded($ruleId, $slotId)) {
            $inSet = !$this->hasSpecialPrice($model);
        }

        return $this->matches($inSet);
    }

    /**
     * Tells whether the shopper already pays less than the regular price for the cart line.
     */
    private function hasSpecialPrice(AbstractItem $item): bool
    {
        $lineItem = $item->getParentItem() ?: $item;
        $children = $lineItem->getChildren();
        $product = $children ? reset($children)->getProduct() : $lineItem->getProduct();
        $priceInfo = $product->getPriceInfo();

        return $priceInfo->getPrice('final_price')->getValue() < $priceInfo->getPrice('regular_price')->getValue();
    }

    /**
     * @inheritdoc
     */
    protected function getOptions(): array
    {
        return array_merge($this->rules->toOptionArray(), $this->slots->toOptionArray());
    }
}
