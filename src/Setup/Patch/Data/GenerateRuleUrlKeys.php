<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Setup\Patch\Data;

use FalconMedia\BundleDeals\Model\Page\UrlKeyGenerator;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Gives existing bundle deals a set page URL key based on their title.
 */
class GenerateRuleUrlKeys implements DataPatchInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly UrlKeyGenerator $urlKeyGenerator
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(Rule::TABLE);
        $rows = $connection->fetchAll(
            $connection->select()->from($table, ['rule_id', 'name', 'title'])->where('url_key IS NULL')
        );
        foreach ($rows as $row) {
            $key = $this->urlKeyGenerator->generate((string) ($row['title'] ?: $row['name']), (int) $row['rule_id']);
            $connection->update($table, ['url_key' => $key], ['rule_id = ?' => (int) $row['rule_id']]);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
