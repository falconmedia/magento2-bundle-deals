<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Reads and writes falconmedia_bundle_deal_index (rule, slot, product) and the manual product links.
 */
class Index
{
    public const TABLE = 'falconmedia_bundle_deal_index';
    public const LINK_TABLE = 'falconmedia_bundle_deal_link';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Returns the current index rows of a rule.
     *
     * @return array<int,array<int,array{position:int,is_entry:bool}>> slot id => product id => row
     */
    public function getRuleRows(int $ruleId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['slot_id', 'product_id', 'position', 'is_entry'])
            ->where('rule_id = ?', $ruleId);
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int) $row['slot_id']][(int) $row['product_id']] = [
                'position' => (int) $row['position'],
                'is_entry' => (bool) $row['is_entry'],
            ];
        }

        return $rows;
    }

    /**
     * Replaces all index rows of a rule.
     *
     * @param array<int,array<int,array{position:int,is_entry:bool}>> $rowsBySlot slot id => product id => row
     */
    public function replaceRule(int $ruleId, array $rowsBySlot): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table, ['rule_id = ?' => $ruleId]);
            $rows = [];
            foreach ($rowsBySlot as $slotId => $products) {
                foreach ($products as $productId => $row) {
                    $rows[] = [
                        'rule_id' => $ruleId,
                        'slot_id' => (int) $slotId,
                        'product_id' => (int) $productId,
                        'position' => (int) $row['position'],
                        'is_entry' => $row['is_entry'] ? 1 : 0,
                    ];
                }
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                $connection->insertMultiple($table, $chunk);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Rules and slots in which the product is an entry, i.e. the PDP of the product shows the bundle.
     *
     * @param int[] $ruleIds limit to these rules, empty for all
     * @return array<int, int> rule id => slot id
     */
    public function getEntrySlotsForProduct(int $productId, array $ruleIds = []): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['rule_id', 'slot_id'])
            ->where('product_id = ?', $productId)
            ->where('is_entry = ?', 1)
            ->order(['rule_id ASC', 'position ASC', 'slot_id ASC']);
        if ($ruleIds) {
            $select->where('rule_id IN (?)', $ruleIds);
        }

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['rule_id']] ??= (int) $row['slot_id'];
        }

        return $result;
    }

    /**
     * Index rows of the rules that contain any of the products.
     *
     * @param int[] $productIds
     * @return array<int, array<int, int[]>> rule id => slot id => product ids (only the given products)
     */
    public function getMembershipForProducts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['rule_id', 'slot_id', 'product_id'])
            ->where('product_id IN (?)', array_values(array_unique($productIds)));

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['rule_id']][(int) $row['slot_id']][] = (int) $row['product_id'];
        }

        return $result;
    }

    /**
     * Product ids of a slot, best position first.
     *
     * @return int[]
     */
    public function getSlotProductIds(int $ruleId, int $slotId, int $limit = 0): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['product_id'])
            ->where('rule_id = ?', $ruleId)
            ->where('slot_id = ?', $slotId)
            ->order(['position ASC', 'product_id ASC']);
        if ($limit > 0) {
            $select->limit($limit);
        }

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Tells whether the product is a member of the slot.
     */
    public function isProductInSlot(int $ruleId, int $slotId, int $productId): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), [new \Zend_Db_Expr('1')])
            ->where('rule_id = ?', $ruleId)
            ->where('slot_id = ?', $slotId)
            ->where('product_id = ?', $productId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Manual links of a rule.
     *
     * @return array<int, array<int, array{position: int, use_as_entry: bool|null}>> slot id => product id => link
     */
    public function getManualLinks(int $ruleId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName(self::LINK_TABLE),
                ['slot_id', 'product_id', 'position', 'use_as_entry']
            )
            ->where('rule_id = ?', $ruleId);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['slot_id']][(int) $row['product_id']] = [
                'position' => (int) $row['position'],
                'use_as_entry' => $row['use_as_entry'] === null ? null : (bool) $row['use_as_entry'],
            ];
        }

        return $result;
    }

    /**
     * Manual links of a product.
     *
     * @return array<string, bool|null> "ruleId:slotId" => entry override
     */
    public function getProductLinks(int $productId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::LINK_TABLE), ['rule_id', 'slot_id', 'use_as_entry'])
            ->where('product_id = ?', $productId);

        $links = [];
        foreach ($connection->fetchAll($select) as $row) {
            $links[(int) $row['rule_id'] . ':' . (int) $row['slot_id']] =
                $row['use_as_entry'] === null ? null : (bool) $row['use_as_entry'];
        }

        return $links;
    }

    /**
     * Replaces the manual links of a product.
     *
     * @param array<string,bool|null> $links "ruleId:slotId" => entry override (null inherits the slot setting)
     * @return int[] rule ids whose links changed
     */
    public function replaceProductLinks(int $productId, array $links): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::LINK_TABLE);
        $current = $this->getProductLinks($productId);
        $wanted = [];
        foreach ($links as $key => $entry) {
            if (preg_match('/^\d+:\d+$/', (string) $key)) {
                $wanted[(string) $key] = $entry === null ? null : (bool) $entry;
            }
        }

        $changedRules = [];
        foreach (array_diff_key($current, $wanted) as $key => $entry) {
            [$ruleId, $slotId] = array_map('intval', explode(':', $key));
            $connection->delete($table, [
                'rule_id = ?' => $ruleId,
                'slot_id = ?' => $slotId,
                'product_id = ?' => $productId,
            ]);
            $changedRules[] = $ruleId;
        }
        foreach ($wanted as $key => $entry) {
            if (array_key_exists($key, $current) && $current[$key] === $entry) {
                continue;
            }
            [$ruleId, $slotId] = array_map('intval', explode(':', $key));
            $connection->insertOnDuplicate($table, [
                'rule_id' => $ruleId,
                'slot_id' => $slotId,
                'product_id' => $productId,
                'position' => 0,
                'use_as_entry' => $entry === null ? null : (int) $entry,
            ], ['use_as_entry']);
            $changedRules[] = $ruleId;
        }

        return array_values(array_unique($changedRules));
    }

    /**
     * Slot ids per rule.
     *
     * @param int[] $ruleIds
     * @return array<int, int[]>
     */
    public function getSlotIdsByRule(array $ruleIds): array
    {
        if (!$ruleIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Slot::TABLE), ['rule_id', 'slot_id'])
            ->where('rule_id IN (?)', $ruleIds)
            ->order(['sort_order ASC', 'slot_id ASC']);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['rule_id']][] = (int) $row['slot_id'];
        }

        return $result;
    }

    /**
     * Discount settings of the slots of the rules.
     *
     * @param int[] $ruleIds
     * @return array<int, array{type: string, amount: float}> slot id => discount
     */
    public function getSlotDiscounts(array $ruleIds): array
    {
        if (!$ruleIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Slot::TABLE), ['slot_id', 'discount_type', 'discount_amount'])
            ->where('rule_id IN (?)', $ruleIds);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int) $row['slot_id']] = [
                'type' => (string) $row['discount_type'],
                'amount' => (float) $row['discount_amount'],
            ];
        }

        return $result;
    }

    /**
     * Ids of the slots that give no set discount to items with a special price.
     *
     * @param int[] $ruleIds
     * @return int[]
     */
    public function getSpecialPriceExcludedSlotIds(array $ruleIds): array
    {
        if (!$ruleIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Slot::TABLE), ['slot_id'])
            ->where('rule_id IN (?)', $ruleIds)
            ->where('exclude_special_price = 1');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Ids of the optional slots of the rules.
     *
     * @param int[] $ruleIds
     * @return int[]
     */
    public function getOptionalSlotIds(array $ruleIds): array
    {
        if (!$ruleIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(Slot::TABLE), ['slot_id'])
            ->where('rule_id IN (?)', $ruleIds)
            ->where('slot_type = ?', \FalconMedia\BundleDeals\Model\Slot::TYPE_OPTIONAL);

        return array_map('intval', $connection->fetchCol($select));
    }
}
