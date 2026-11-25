<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Console\Command;

use FalconMedia\BundleDeals\Model\Import\Amasty\Importer;
use FalconMedia\BundleDeals\Model\Import\Amasty\PackMapper;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Imports Amasty "Who Bought This Also Bought" packs as bundle deals.
 */
class ImportAmasty extends Command
{
    private const OPT_DRY_RUN = 'dry-run';
    private const OPT_PACK = 'pack';
    private const OPT_KEEP_STATUS = 'keep-status';
    private const OPT_CHILDREN_AS_ENTRY = 'children-as-entry';
    private const OPT_FORCE = 'force';

    private const WARNING_LABELS = [
        PackMapper::WARN_APPLY_CONDITION_ANY => 'apply condition "any products"',
        PackMapper::WARN_CONDITIONAL_DISCOUNT => 'conditional discount',
        PackMapper::WARN_CART_MESSAGE => 'cart message not supported',
        PackMapper::WARN_QTY => 'item quantity > 1',
        PackMapper::WARN_MISSING_PRODUCT => 'missing product',
        PackMapper::WARN_PER_ITEM_DISCOUNT => 'per-item discount used',
        PackMapper::WARN_CHILD_TO_PARENT => 'variant mapped to its parent',
    ];

    /**
     * @var Importer
     */
    private $importer;

    /**
     * @var State
     */
    private $state;

    public function __construct(Importer $importer, State $state)
    {
        $this->importer = $importer;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('falconmedia:bundle-deals:import-amasty')
            ->setDescription('Import Amasty bundle packs as bundle deals')
            ->addOption(self::OPT_DRY_RUN, null, InputOption::VALUE_NONE, 'Show what would happen, write nothing')
            ->addOption(self::OPT_PACK, null, InputOption::VALUE_REQUIRED, 'Comma separated Amasty pack ids')
            ->addOption(self::OPT_KEEP_STATUS, null, InputOption::VALUE_NONE, 'Copy the Amasty status (default active)')
            ->addOption(
                self::OPT_CHILDREN_AS_ENTRY,
                null,
                InputOption::VALUE_NONE,
                'Also show the bundle deal on the item product pages'
            )
            ->addOption(
                self::OPT_FORCE,
                null,
                InputOption::VALUE_NONE,
                'Overwrite imported deals, including admin edits and manual links'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (LocalizedException $e) {
            // Area code is already set.
        }

        $dryRun = (bool) $input->getOption(self::OPT_DRY_RUN);
        $force = (bool) $input->getOption(self::OPT_FORCE);
        $keepStatus = (bool) $input->getOption(self::OPT_KEEP_STATUS);
        $packFilter = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $input->getOption(self::OPT_PACK))
        )));

        try {
            $plan = $this->importer->plan(
                $keepStatus,
                (bool) $input->getOption(self::OPT_CHILDREN_AS_ENTRY)
            );
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $counts = [
            Importer::ACTION_CREATE => 0,
            Importer::ACTION_UPDATE => 0,
            Importer::ACTION_KEEP => 0,
            Importer::ACTION_SKIP => 0,
            'error' => 0,
        ];
        $warnings = array_fill_keys(array_keys(self::WARNING_LABELS), 0);
        $packs = $packFilter ? array_intersect_key($plan['packs'], array_flip($packFilter)) : $plan['packs'];
        foreach ($packs as $packId => $mapped) {
            foreach (array_unique(array_column($mapped['warnings'], 'type')) as $type) {
                $warnings[$type] = ($warnings[$type] ?? 0) + 1;
            }
            $action = $this->importer->getPlannedAction($mapped, $force);
            if (in_array($action, [Importer::ACTION_CREATE, Importer::ACTION_UPDATE], true) && !$dryRun) {
                try {
                    $action = $this->importer->write($mapped, $keepStatus);
                } catch (\Exception $e) {
                    $counts['error']++;
                    $output->writeln(sprintf('<error>pack %d: %s</error>', $packId, $e->getMessage()));
                    continue;
                }
            }
            $counts[$action]++;
            $output->writeln($this->formatLine((int) $packId, $action, $mapped));
        }

        if ($dryRun) {
            $this->printGroups($output, $plan['groups'], $plan['packs']);
        }
        $this->printSummary($output, $counts, $warnings, $dryRun);

        return $counts['error'] ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Formats the result line of one pack.
     *
     * @param array<string,mixed> $mapped
     */
    private function formatLine(int $packId, string $action, array $mapped): string
    {
        if ($action === Importer::ACTION_SKIP) {
            return sprintf('pack %d: skip (%s)', $packId, $mapped['reason']);
        }
        if ($action === Importer::ACTION_KEEP) {
            return sprintf('pack %d: keep (already imported, --force overwrites)', $packId);
        }
        $warningTypes = array_unique(array_column($mapped['warnings'], 'type'));

        return sprintf(
            'pack %d: %s "%s", %d slots%s%s',
            $packId,
            $action,
            $mapped['rule']['title'],
            count($mapped['slots']),
            $mapped['rule']['color_group'] ? ', colour group ' . $mapped['rule']['color_group'] : '',
            $warningTypes ? ', warnings: ' . implode(', ', $warningTypes) : ''
        );
    }

    /**
     * Prints the proposed colour groups.
     *
     * @param array<string,array<string,mixed>> $groups
     * @param array<int,array<string,mixed>> $packs
     */
    private function printGroups(OutputInterface $output, array $groups, array $packs): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>Proposed colour groups: %d</info>', count($groups)));
        $table = new Table($output);
        $table->setHeaders(['Group', 'Source', 'Pack (sort order)']);
        foreach ($groups as $code => $group) {
            $members = [];
            foreach ($group['members'] as $packId => $sortOrder) {
                $members[] = sprintf('%d "%s" (%d)', $packId, $packs[$packId]['rule']['title'] ?? '', $sortOrder);
            }
            $table->addRow([$code, $group['source'], implode("\n", $members)]);
        }
        $table->render();
    }

    /**
     * Prints the summary table.
     *
     * @param array<string,int> $counts
     * @param array<string,int> $warnings
     */
    private function printSummary(OutputInterface $output, array $counts, array $warnings, bool $dryRun): void
    {
        $output->writeln('');
        $output->writeln($dryRun ? '<info>Summary (dry run, nothing written)</info>' : '<info>Summary</info>');
        $table = new Table($output);
        $table->setHeaders(['Count', 'Packs']);
        $table->addRow(['created', $counts[Importer::ACTION_CREATE]]);
        $table->addRow(['updated', $counts[Importer::ACTION_UPDATE]]);
        $table->addRow(['kept (already imported)', $counts[Importer::ACTION_KEEP]]);
        $table->addRow(['skipped', $counts[Importer::ACTION_SKIP]]);
        $table->addRow(['errors', $counts['error']]);
        foreach (self::WARNING_LABELS as $type => $label) {
            $table->addRow(['warning: ' . $label, $warnings[$type] ?? 0]);
        }
        $table->render();
    }
}
