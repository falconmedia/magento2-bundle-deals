<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Import\Amasty;

use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\Slot;

/**
 * Maps one Amasty "Who Bought This Also Bought" pack to bundle deal rule and slot data.
 */
class PackMapper
{
    public const REFERENCE_PREFIX = 'amasty_pack:';
    public const ALL_GROUPS = 32000;

    public const DISCOUNT_FIXED = 0;
    public const DISCOUNT_PERCENT = 1;
    public const DISCOUNT_CONDITIONAL = 2;

    public const CONDITION_ANY_PRODUCTS = 0;

    public const WARN_APPLY_CONDITION_ANY = 'apply_condition_any';
    public const WARN_CONDITIONAL_DISCOUNT = 'conditional_discount';
    public const WARN_CART_MESSAGE = 'cart_message';
    public const WARN_QTY = 'qty_gt_1';
    public const WARN_MISSING_PRODUCT = 'missing_product';
    public const WARN_PER_ITEM_DISCOUNT = 'per_item_discount';
    public const WARN_CHILD_TO_PARENT = 'child_to_parent';

    public const STATUS_OK = 'ok';
    public const STATUS_SKIP = 'skip';

    /**
     * Maps a pack row to rule data, slot data and warnings.
     *
     * The pack row holds the amasty_mostviewed_pack columns plus "parent_ids" (int[]) and "store_ids" (int[]).
     * Products are keyed by id with "sku" and "name".
     *
     * @param array<string,mixed> $pack
     * @param array<int,array<string,mixed>> $products keyed by id: sku, name and optional parent_id
     * @return array{
     *     status: string,
     *     reason: string,
     *     pack_id: int,
     *     warnings: array<int, array{type: string, message: string}>,
     *     rule: array<string, mixed>,
     *     slots: array<int, array<string, mixed>>,
     *     entry_product_ids: int[]
     * }
     */
    public function map(array $pack, array $products, bool $keepStatus = false, bool $childrenAsEntry = false): array
    {
        $packId = (int) $pack['pack_id'];
        $result = [
            'status' => self::STATUS_OK,
            'reason' => '',
            'pack_id' => $packId,
            'warnings' => [],
            'rule' => [],
            'slots' => [],
            'entry_product_ids' => [],
        ];

        $discountType = $this->mapDiscountType((int) ($pack['discount_type'] ?? self::DISCOUNT_PERCENT));
        if ((int) ($pack['discount_type'] ?? 0) === self::DISCOUNT_CONDITIONAL) {
            $result['warnings'][] = $this->warning(
                self::WARN_CONDITIONAL_DISCOUNT,
                'conditional discount converted to a percentage of the pack amount'
            );
        }
        if ((int) ($pack['apply_condition'] ?? 0) === self::CONDITION_ANY_PRODUCTS) {
            $result['warnings'][] = $this->warning(
                self::WARN_APPLY_CONDITION_ANY,
                'apply condition "any products" converted to all-items'
            );
        }
        if (trim((string) ($pack['cart_message'] ?? '')) !== '') {
            $result['warnings'][] = $this->warning(self::WARN_CART_MESSAGE, 'cart message is not supported');
        }

        $parentIds = [];
        foreach ((array) ($pack['parent_ids'] ?? []) as $parentId) {
            if (isset($products[(int) $parentId])) {
                $parentIds[] = (int) $parentId;
            } else {
                $result['warnings'][] = $this->warning(
                    self::WARN_MISSING_PRODUCT,
                    sprintf('main product %d does not exist and is left out', (int) $parentId)
                );
            }
        }
        $parentIds = array_values(array_unique($this->toParents($parentIds, $products, $result)));

        $itemIds = $this->parseIds((string) ($pack['product_ids'] ?? ''));
        $missingItems = array_values(array_filter($itemIds, static function (int $id) use ($products): bool {
            return !isset($products[$id]);
        }));
        if ($missingItems) {
            $result['warnings'][] = $this->warning(
                self::WARN_MISSING_PRODUCT,
                sprintf('item product(s) %s do not exist', implode(', ', $missingItems))
            );

            return $this->skip($result, sprintf('missing item product(s) %s', implode(', ', $missingItems)));
        }
        if (!$parentIds) {
            return $this->skip($result, 'no existing main products');
        }
        $itemSourceIds = [];
        foreach ($itemIds as $itemId) {
            $targetId = $this->toParents([$itemId], $products, $result)[0];
            if (!isset($itemSourceIds[$targetId]) && !in_array($targetId, $parentIds, true)) {
                $itemSourceIds[$targetId] = $itemId;
            }
        }

        $packAmount = $this->toAmount($pack['discount_amount'] ?? null);
        $itemInfo = $this->decodeProductsInfo((string) ($pack['products_info'] ?? ''));
        $title = trim((string) ($pack['block_title'] ?? '')) ?: trim((string) ($pack['name'] ?? ''));
        $title = $title !== '' ? $title : sprintf('Amasty pack %d', $packId);

        $slots = [];
        $applyForParent = (int) ($pack['apply_for_parent'] ?? 0) === 1;
        $slots[] = [
            'title' => count($parentIds) === 1 ? $products[$parentIds[0]]['name'] : $title,
            'skus' => array_map(static function (int $id) use ($products): string {
                return $products[$id]['sku'];
            }, $parentIds),
            'use_as_entry' => 1,
            'discount_type' => $applyForParent ? $discountType : Slot::DISCOUNT_NONE,
            'discount_amount' => $applyForParent ? $packAmount : 0.0,
        ];

        foreach ($itemSourceIds as $itemId => $sourceId) {
            $info = $itemInfo[$sourceId] ?? [];
            $amount = $packAmount;
            $itemAmount = $info['discount_amount'] ?? null;
            if ($itemAmount !== null && trim((string) $itemAmount) !== '') {
                $amount = $this->toAmount($itemAmount);
                $result['warnings'][] = $this->warning(
                    self::WARN_PER_ITEM_DISCOUNT,
                    sprintf('item %d uses its own discount amount %s', $itemId, (string) $itemAmount)
                );
            }
            if ((int) ($info['quantity'] ?? 1) > 1) {
                $result['warnings'][] = $this->warning(
                    self::WARN_QTY,
                    sprintf('item %d has quantity %d, imported as 1', $itemId, (int) $info['quantity'])
                );
            }
            $slots[] = [
                'title' => $products[$itemId]['name'],
                'skus' => [$products[$itemId]['sku']],
                'use_as_entry' => $childrenAsEntry ? 1 : 0,
                'discount_type' => $amount > 0 ? $discountType : Slot::DISCOUNT_NONE,
                'discount_amount' => $amount,
            ];
        }

        if (count($slots) < 2) {
            return $this->skip($result, 'fewer than two slots');
        }
        foreach ($slots as $index => $slot) {
            $slots[$index]['sort_order'] = ($index + 1) * 10;
            $slots[$index]['sort_by'] = SortBy::POSITION;
        }

        $result['rule'] = [
            'name' => trim((string) ($pack['name'] ?? '')) ?: $title,
            'title' => $title,
            'is_active' => $keepStatus ? ((int) ($pack['status'] ?? 0) === 1 ? 1 : 0) : 1,
            'priority' => (int) ($pack['priority'] ?? 0),
            'from_date' => $this->toDate($pack['date_from'] ?? null),
            'to_date' => $this->toDate($pack['date_to'] ?? null),
            'exclude_other_discounts' => 1,
            'customer_group_ids' => $this->mapCustomerGroups((string) ($pack['customer_group_ids'] ?? '')),
            'store_ids' => $this->mapStoreIds((array) ($pack['store_ids'] ?? [])),
            'external_reference' => self::REFERENCE_PREFIX . $packId,
        ];
        $result['slots'] = $slots;
        $result['entry_product_ids'] = $parentIds;

        return $result;
    }

    /**
     * Maps Amasty customer group ids to bundle deal groups; an empty list means all groups.
     *
     * @return int[]
     */
    public function mapCustomerGroups(string $value): array
    {
        $ids = [];
        foreach (explode(',', $value) as $id) {
            $id = trim($id);
            if ($id === '' || !is_numeric($id)) {
                continue;
            }
            if ((int) $id === self::ALL_GROUPS) {
                return [];
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    public function mapDiscountType(int $type): string
    {
        return $type === self::DISCOUNT_FIXED ? Slot::DISCOUNT_FIXED : Slot::DISCOUNT_PERCENT;
    }

    /**
     * Returns store ids, [0] for all store views.
     *
     * @param array<int,int|string> $storeIds
     * @return int[]
     */
    private function mapStoreIds(array $storeIds): array
    {
        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));

        return !$storeIds || in_array(0, $storeIds, true) ? [0] : $storeIds;
    }

    /**
     * Parses a comma separated id list keeping its order.
     *
     * @return int[]
     */
    private function parseIds(string $value): array
    {
        $ids = array_filter(array_map('intval', explode(',', $value)));

        return array_values(array_unique($ids));
    }

    /**
     * Decodes the products_info JSON keyed by item product id.
     *
     * @return array<int,array<string,mixed>>
     */
    private function decodeProductsInfo(string $value): array
    {
        $decoded = $value !== '' ? json_decode($value, true) : null;
        if (!is_array($decoded)) {
            return [];
        }
        $info = [];
        foreach ($decoded as $productId => $row) {
            if (is_array($row)) {
                $info[(int) ($row['entity_id'] ?? $productId)] = $row;
            }
        }

        return $info;
    }

    /**
     * Converts an amount string to a non negative float.
     *
     * @param mixed $value
     */
    private function toAmount($value): float
    {
        return max(0.0, (float) str_replace(',', '.', trim((string) $value)));
    }

    /**
     * Converts a datetime value to a Y-m-d date, or null.
     *
     * @param mixed $value
     */
    private function toDate($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strpos($value, '0000-00-00') === 0 ? null : substr($value, 0, 10);
    }

    /**
     * Builds a warning entry.
     *
     * @return array{type:string, message:string}
     */
    private function warning(string $type, string $message): array
    {
        return ['type' => $type, 'message' => $message];
    }

    /**
     * Replaces simple products that are only sold through their configurable parent by that parent.
     *
     * @param int[] $productIds
     * @param array<int,array<string,mixed>> $products
     * @param array<string,mixed> $result receives a warning per replaced product
     * @return int[]
     */
    private function toParents(array $productIds, array $products, array &$result): array
    {
        $mapped = [];
        foreach ($productIds as $productId) {
            $parentId = (int) ($products[$productId]['parent_id'] ?? 0);
            if ($parentId > 0 && isset($products[$parentId])) {
                $result['warnings'][] = $this->warning(
                    self::WARN_CHILD_TO_PARENT,
                    sprintf('product %d is a variant, its parent %d is used', $productId, $parentId)
                );
                $productId = $parentId;
            }
            $mapped[] = $productId;
        }

        return $mapped;
    }

    /**
     * Marks a mapping result as skipped.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function skip(array $result, string $reason): array
    {
        $result['status'] = self::STATUS_SKIP;
        $result['reason'] = $reason;

        return $result;
    }
}
