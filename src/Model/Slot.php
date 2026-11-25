<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model;

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\CatalogRule\Model\Rule\Condition\CombineFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection as ActionCollection;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;

/**
 * One member of a bundle deal; its conditions select the products that can fill it.
 *
 * @method string getTitle()
 * @method string getSortBy()
 */
class Slot extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'falconmedia_bundle_deal_slot';

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        private readonly CombineFactory $combineFactory,
        private readonly ActionCollectionFactory $actionCollectionFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?ExtensionAttributesFactory $extensionFactory = null,
        ?AttributeValueFactory $customAttributeFactory = null,
        ?Json $serializer = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data,
            $extensionFactory,
            $customAttributeFactory,
            $serializer
        );
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(ResourceModel\Slot::class);
        $this->setIdFieldName('slot_id');
    }

    /**
     * Returns a new empty condition group.
     */
    public function getConditionsInstance(): Combine
    {
        return $this->combineFactory->create();
    }

    /**
     * Returns a new empty action collection.
     */
    public function getActionsInstance(): ActionCollection
    {
        return $this->actionCollectionFactory->create();
    }

    public const DISCOUNT_NONE = 'none';
    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_FIXED = 'fixed';

    public const TYPE_FIXED = 'fixed';
    public const TYPE_VARIABLE = 'variable';
    public const TYPE_OPTIONAL = 'optional';

    public function getRuleId(): int
    {
        return (int) $this->getData('rule_id');
    }

    public function isEntry(): bool
    {
        return (bool) $this->getData('use_as_entry');
    }

    public function getDiscountType(): string
    {
        $type = (string) $this->getData('discount_type');

        return in_array($type, [self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED], true) && $this->getDiscountAmount() > 0
            ? $type
            : self::DISCOUNT_NONE;
    }

    /**
     * Slot type: fixed (one product), variable (the shopper picks a product) or optional (the shopper may skip it).
     */
    public function getSlotType(): string
    {
        $type = (string) $this->getData('slot_type');

        return in_array($type, [self::TYPE_FIXED, self::TYPE_OPTIONAL], true) ? $type : self::TYPE_VARIABLE;
    }

    /**
     * Whether a set is complete without a product in this slot.
     */
    public function isOptional(): bool
    {
        return $this->getSlotType() === self::TYPE_OPTIONAL;
    }

    /**
     * Whether items that already have a special price get no set discount from this slot.
     */
    public function isSpecialPriceExcluded(): bool
    {
        return (bool) $this->getData('exclude_special_price');
    }

    public function getDiscountAmount(): float
    {
        return max(0.0, (float) $this->getData('discount_amount'));
    }

    /**
     * Returns the id of the generated cart price rule, if any.
     */
    public function getSalesRuleId(): ?int
    {
        $id = $this->getData('sales_rule_id');

        return $id ? (int) $id : null;
    }
}
