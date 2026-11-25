<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Cron;

use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;

/**
 * Hourly rebuild, so products that start or stop matching slot conditions (new products, attribute
 * changes, date windows) show up without saving the bundle deal.
 */
class Reindex
{
    public function __construct(
        private readonly RuleIndexer $ruleIndexer
    ) {
    }

    public function execute(): void
    {
        $this->ruleIndexer->reindexAll();
    }
}
