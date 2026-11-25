<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Bundle deal: a set of equal members (slots); every member can be the entry on its own product page.
 *
 * @method string getName()
 * @method string|null getTitle()
 * @method string|null getFromDate()
 * @method string|null getToDate()
 */
class Rule extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'falconmedia_bundle_deal';

    /**
     * @var string
     */
    protected $_eventPrefix = 'falconmedia_bundle_deal_rule';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Rule::class);
        $this->setIdFieldName('rule_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->getData('is_active');
    }

    public function isExclusive(): bool
    {
        return (bool) $this->getData('exclude_other_discounts');
    }

    public function getPriority(): int
    {
        return (int) $this->getData('priority');
    }

    /**
     * Returns the ids of the store views of the bundle deal.
     *
     * @return int[]
     */
    public function getStoreIds(): array
    {
        $storeIds = $this->getData('store_ids');
        if ($storeIds === null || $storeIds === '') {
            return [];
        }
        if (!is_array($storeIds)) {
            $storeIds = explode(',', (string) $storeIds);
        }

        return array_values(array_unique(array_map('intval', $storeIds)));
    }

    /**
     * Returns the customer group ids the bundle deal applies to; an empty list means all groups.
     *
     * @return int[]
     */
    public function getCustomerGroupIds(): array
    {
        $groupIds = $this->getData('customer_group_ids');
        if ($groupIds === null || $groupIds === '' || $groupIds === []) {
            return [];
        }
        if (!is_array($groupIds)) {
            $groupIds = explode(',', (string) $groupIds);
        }
        $groupIds = array_filter(
            array_map('trim', array_map('strval', $groupIds)),
            static fn (string $id): bool => $id !== ''
        );

        return array_values(array_unique(array_map('intval', $groupIds)));
    }

    /**
     * Sets the customer group ids; an empty list means all groups.
     *
     * @param int[] $groupIds
     * @return $this
     */
    public function setCustomerGroupIds(array $groupIds): self
    {
        return $this->setData('customer_group_ids', $groupIds ? implode(',', array_map('intval', $groupIds)) : null);
    }

    public function appliesToCustomerGroup(int $customerGroupId): bool
    {
        $groupIds = $this->getCustomerGroupIds();

        return !$groupIds || in_array($customerGroupId, $groupIds, true);
    }

    /**
     * Returns the reference of the system the bundle deal was imported from, if any.
     */
    public function getExternalReference(): ?string
    {
        $reference = (string) $this->getData('external_reference');

        return $reference !== '' ? $reference : null;
    }

    /**
     * Sets the reference of the system the bundle deal was imported from.
     *
     * @return $this
     */
    public function setExternalReference(?string $reference): self
    {
        return $this->setData('external_reference', $reference !== '' ? $reference : null);
    }

    /**
     * Returns the code of the colour group the bundle deal belongs to, if any.
     */
    public function getColorGroup(): ?string
    {
        $group = trim((string) $this->getData('color_group'));

        return $group !== '' ? $group : null;
    }

    /**
     * Sets the colour group code.
     *
     * @return $this
     */
    public function setColorGroup(?string $group): self
    {
        $group = $group === null ? '' : trim($group);

        return $this->setData('color_group', $group !== '' ? $group : null);
    }

    /**
     * Returns the option id of the color attribute that labels this bundle deal in its colour group.
     */
    public function getColorOptionId(): ?int
    {
        $optionId = (int) $this->getData('color_option_id');

        return $optionId > 0 ? $optionId : null;
    }

    /**
     * Sets the option id of the color attribute.
     *
     * @return $this
     */
    public function setColorOptionId(?int $optionId): self
    {
        return $this->setData('color_option_id', $optionId ?: null);
    }

    public function getColorSortOrder(): ?int
    {
        $sortOrder = $this->getData('color_sort_order');

        return $sortOrder === null || $sortOrder === '' ? null : (int) $sortOrder;
    }

    /**
     * Sets the sort order within the colour group.
     *
     * @return $this
     */
    public function setColorSortOrder(?int $sortOrder): self
    {
        return $this->setData('color_sort_order', $sortOrder);
    }

    /**
     * Returns the cache tags of the bundle deal.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId(), self::CACHE_TAG];
    }

    /**
     * URL key of the set page, or null when the deal has no set page.
     */
    public function getUrlKey(): ?string
    {
        $key = trim((string) $this->getData('url_key'));

        return $key === '' ? null : $key;
    }

    /**
     * Set page description (plain text or simple HTML).
     */
    public function getDescription(): string
    {
        return (string) $this->getData('description');
    }
}
