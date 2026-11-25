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
use FalconMedia\BundleDeals\Model\Set\CartSetResolver;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Shared select/operator handling for the bundle deal cart price rule conditions.
 */
abstract class AbstractBundleCondition extends AbstractCondition
{
    public const ATTRIBUTE = 'bundle_deal_rule';

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        protected readonly CartSetResolver $cartSetResolver,
        protected readonly Rules $rules,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function loadOperatorOptions()
    {
        $this->setOperatorOption([
            '==' => __('is'),
            '!=' => __('is not'),
        ]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getInputType()
    {
        return 'select';
    }

    /**
     * @inheritdoc
     */
    public function getValueElementType()
    {
        return 'select';
    }

    /**
     * Returns the bundle deals as select options.
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            $this->setData('value_select_options', $this->getOptions());
        }

        return $this->getData('value_select_options');
    }

    /**
     * @inheritdoc
     */
    public function getExplicitApply(): bool
    {
        return false;
    }

    /**
     * Returns the options of the condition value.
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    protected function getOptions(): array
    {
        return $this->rules->toOptionArray();
    }

    /**
     * Returns the id of the bundle deal the condition refers to.
     */
    protected function getBundleRuleId(): int
    {
        return (int) explode(':', (string) $this->getValueParsed())[0];
    }

    /**
     * Returns the id of the slot the condition refers to, if any.
     */
    protected function getBundleSlotId(): ?int
    {
        $parts = explode(':', (string) $this->getValueParsed());

        return isset($parts[1]) ? (int) $parts[1] : null;
    }

    /**
     * Compares the set membership with the configured operator and value.
     */
    protected function matches(bool $inSet): bool
    {
        return $this->getOperatorForValidate() === '!=' ? !$inSet : $inSet;
    }
}
