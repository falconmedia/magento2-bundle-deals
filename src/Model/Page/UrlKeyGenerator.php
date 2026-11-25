<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Page;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filter\FilterManager;

/**
 * Turns text into a set page URL key that no other bundle deal uses.
 */
class UrlKeyGenerator
{
    public function __construct(
        private readonly FilterManager $filterManager,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Normalises user input to a URL key; empty when nothing usable is left.
     */
    public function normalize(string $text): string
    {
        $key = (string) $this->filterManager->translitUrl(trim($text));

        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($key)) ?? '', '-');
    }

    /**
     * Returns a free URL key based on the text, with a numeric suffix when the key is taken.
     *
     * @param int $ruleId rule that may keep its own key; 0 for a new rule
     */
    public function generate(string $text, int $ruleId = 0): string
    {
        $base = $this->normalize($text) ?: 'set';
        $key = $base;
        for ($suffix = 2; $this->isTaken($key, $ruleId); $suffix++) {
            $key = $base . '-' . $suffix;
        }

        return $key;
    }

    /**
     * Whether another bundle deal uses the URL key.
     */
    public function isTaken(string $key, int $ruleId = 0): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(RuleResource::TABLE), ['rule_id'])
            ->where('url_key = ?', $key)
            ->where('rule_id <> ?', $ruleId)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }
}
