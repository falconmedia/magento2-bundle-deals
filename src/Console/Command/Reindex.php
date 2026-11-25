<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Console\Command;

use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rebuilds the bundle deal product index for all rules or the given rule ids.
 */
class Reindex extends Command
{
    public function __construct(
        private readonly RuleIndexer $ruleIndexer,
        private readonly State $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('falconmedia:bundle-deals:reindex')
            ->setDescription('Rebuild the bundle deal product index')
            ->addArgument('rule_ids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Bundle deal rule ids');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area code is already set.
        }

        $ruleIds = array_filter(array_map('intval', (array) $input->getArgument('rule_ids')));
        if (!$ruleIds) {
            $count = $this->ruleIndexer->reindexAll();
            $output->writeln(sprintf('<info>Reindexed %d bundle deal rule(s).</info>', $count));
            return Command::SUCCESS;
        }
        foreach ($ruleIds as $ruleId) {
            $this->ruleIndexer->reindexRule($ruleId);
        }
        $output->writeln(sprintf('<info>Reindexed %d bundle deal rule(s).</info>', count($ruleIds)));

        return Command::SUCCESS;
    }
}
