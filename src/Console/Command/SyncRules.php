<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Console\Command;

use FalconMedia\BundleDeals\Model\Discount\GeneratedRuleRegistry;
use FalconMedia\BundleDeals\Model\Discount\SalesRuleSynchronizer;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule\CollectionFactory;
use FalconMedia\BundleDeals\Model\RuleFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rewrites the generated cart price rules of all bundle deals and deactivates the ones no slot refers to anymore.
 */
class SyncRules extends Command
{
    private const OPT_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly SalesRuleSynchronizer $synchronizer,
        private readonly GeneratedRuleRegistry $generatedRules,
        private readonly CollectionFactory $collectionFactory,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly State $state
    ) {
        parent::__construct();
    }

    /**
     * Defines the command name, description and options.
     */
    protected function configure(): void
    {
        $this->setName('falconmedia:bundle-deals:sync-rules')
            ->setDescription('Rewrite the generated cart price rules and deactivate orphaned ones')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Only list the orphaned cart price rules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area code is already set.
        }

        if ($input->getOption(self::OPT_DRY_RUN)) {
            $orphans = $this->synchronizer->getOrphanedRuleIds();
            $output->writeln(sprintf(
                '<info>%d active orphaned cart price rule(s): %s</info>',
                count($orphans),
                $orphans ? implode(', ', $orphans) : '-'
            ));
            return Command::SUCCESS;
        }

        $synced = 0;
        foreach ($this->collectionFactory->create()->getAllIds() as $ruleId) {
            $rule = $this->ruleFactory->create();
            $this->ruleResource->load($rule, (int) $ruleId);
            if (!$rule->getId()) {
                continue;
            }
            $this->synchronizer->syncRule($rule);
            $synced++;
        }
        $this->generatedRules->reset();
        $orphans = $this->synchronizer->disableOrphanedRules();

        $output->writeln(sprintf('<info>Synchronized %d bundle deal(s).</info>', $synced));
        $output->writeln(sprintf(
            '<info>Deactivated %d orphaned cart price rule(s): %s</info>',
            count($orphans),
            $orphans ? implode(', ', $orphans) : '-'
        ));

        return Command::SUCCESS;
    }
}
