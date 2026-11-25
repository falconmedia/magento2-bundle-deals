<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Page;

use Magento\Framework\App\ResourceConnection;

/**
 * Approved product reviews and their rating summary for the items of a set.
 */
class ReviewProvider
{
    private const PRODUCT_ENTITY_CODE = 'product';
    private const STATUS_APPROVED = 1;
    private const PERCENT_PER_STAR = 20;

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Review count and average rating (0 to 5) over the given products.
     *
     * @param int[] $productIds
     * @return array{count: int, average: float}
     */
    public function getSummary(array $productIds, int $storeId): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return ['count' => 0, 'average' => 0.0];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['s' => $this->resource->getTableName('review_entity_summary')],
                [
                    'count' => new \Zend_Db_Expr('SUM(s.reviews_count)'),
                    'average' => new \Zend_Db_Expr('SUM(s.reviews_count * s.rating_summary) / SUM(s.reviews_count)'),
                ]
            )
            ->where('s.entity_type = ?', $this->getProductEntityId())
            ->where('s.store_id = ?', $storeId)
            ->where('s.entity_pk_value IN (?)', $productIds)
            ->where('s.reviews_count > 0');
        $row = $connection->fetchRow($select) ?: [];
        $count = (int) ($row['count'] ?? 0);

        return [
            'count' => $count,
            'average' => $count > 0 ? round((float) $row['average'] / self::PERCENT_PER_STAR, 1) : 0.0,
        ];
    }

    /**
     * The latest approved reviews of the given products.
     *
     * @param int[] $productIds
     * @return array<int,array{productId: int, title: string, detail: string, nickname: string, date: string, stars: float}>
     */
    public function getLatest(array $productIds, int $storeId, int $limit = 6): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $votes = $connection->select()
            ->from($this->resource->getTableName('rating_option_vote'), [
                'review_id',
                'stars' => new \Zend_Db_Expr('AVG(percent)'),
            ])
            ->group('review_id');
        $select = $connection->select()
            ->from(['r' => $this->resource->getTableName('review')], ['entity_pk_value', 'created_at'])
            ->join(
                ['d' => $this->resource->getTableName('review_detail')],
                'd.review_id = r.review_id',
                ['title', 'detail', 'nickname']
            )
            ->join(['rs' => $this->resource->getTableName('review_store')], 'rs.review_id = r.review_id', [])
            ->joinLeft(['v' => $votes], 'v.review_id = r.review_id', ['stars'])
            ->where('r.status_id = ?', self::STATUS_APPROVED)
            ->where('r.entity_id = ?', $this->getProductEntityId())
            ->where('r.entity_pk_value IN (?)', $productIds)
            ->where('rs.store_id IN (?)', [0, $storeId])
            ->order('r.created_at DESC')
            ->limit($limit);

        $reviews = [];
        foreach ($connection->fetchAll($select) as $row) {
            $reviews[] = [
                'productId' => (int) $row['entity_pk_value'],
                'title' => (string) $row['title'],
                'detail' => (string) $row['detail'],
                'nickname' => (string) $row['nickname'],
                'date' => (string) $row['created_at'],
                'stars' => round((float) $row['stars'] / self::PERCENT_PER_STAR, 1),
            ];
        }

        return $reviews;
    }

    private function getProductEntityId(): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('review_entity'), 'entity_id')
                ->where('entity_code = ?', self::PRODUCT_ENTITY_CODE)
        );
    }
}
