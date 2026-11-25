<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Console\Command;

use FalconMedia\BundleDeals\Model\Cli\SlotDefinitionParser;
use FalconMedia\BundleDeals\Model\Import\SkuConditionBuilder;
use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;
use FalconMedia\BundleDeals\Model\Page\UrlKeyGenerator;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates a bundle deal with SKU based slots, for setting up test or sample bundles after a deploy.
 */
class Create extends Command
{
    private const ARG_NAME = 'name';
    private const OPT_TITLE = 'title';
    private const OPT_SLOT = 'slot';
    private const OPT_STORE = 'store';
    private const OPT_PRIORITY = 'priority';
    private const OPT_ALLOW_OTHER = 'allow-other-discounts';
    private const OPT_INACTIVE = 'inactive';
    private const OPT_URL_KEY = 'url-key';
    private const OPT_DESCRIPTION = 'description';

    public function __construct(
        private readonly SlotDefinitionParser $parser,
        private readonly RuleFactory $ruleFactory,
        private readonly RuleResource $ruleResource,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource,
        private readonly RuleIndexer $ruleIndexer,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly ResourceConnection $resource,
        private readonly State $state,
        private readonly SkuConditionBuilder $skuConditionBuilder,
        private readonly UrlKeyGenerator $urlKeyGenerator
    ) {
        parent::__construct();
    }

    /**
     * Defines the command name, arguments and options.
     */
    protected function configure(): void
    {
        $this->setName('falconmedia:bundle-deals:create')
            ->setDescription('Create a bundle deal with SKU based slots')
            ->addArgument(self::ARG_NAME, InputArgument::REQUIRED, 'Admin name of the bundle deal')
            ->addOption(self::OPT_TITLE, null, InputOption::VALUE_REQUIRED, 'Storefront title')
            ->addOption(
                self::OPT_SLOT,
                's',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Slot "Title|SKU1,SKU2|discount|entry|sort|type"; discount none, 10% or 5 (fixed), entry 1/0, type fixed/variable/optional'
            )
            ->addOption(self::OPT_STORE, null, InputOption::VALUE_REQUIRED, 'Comma separated store codes', 'admin')
            ->addOption(self::OPT_PRIORITY, null, InputOption::VALUE_REQUIRED, 'Priority, lower wins', '0')
            ->addOption(self::OPT_ALLOW_OTHER, null, InputOption::VALUE_NONE, 'Let other cart price rules discount set items too')
            ->addOption(self::OPT_URL_KEY, null, InputOption::VALUE_REQUIRED, 'URL key of the set page, default from the title')
            ->addOption(self::OPT_DESCRIPTION, null, InputOption::VALUE_REQUIRED, 'Description shown on the set page')
            ->addOption(self::OPT_INACTIVE, null, InputOption::VALUE_NONE, 'Create the bundle deal inactive');
    }

    /**
     * Creates a bundle deal with its slots from the command line input.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (LocalizedException) {
            // Area code is already set.
        }

        try {
            $slots = array_map([$this->parser, 'parse'], (array) $input->getOption(self::OPT_SLOT));
            if (count($slots) < 2) {
                throw new \InvalidArgumentException('A bundle deal needs at least two --slot options.');
            }
            $this->assertSkusExist($slots);
            $storeIds = $this->resolveStoreIds((string) $input->getOption(self::OPT_STORE));
        } catch (\InvalidArgumentException | LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $name = (string) $input->getArgument(self::ARG_NAME);
        $urlKey = $this->urlKeyGenerator->normalize((string) $input->getOption(self::OPT_URL_KEY));
        if ($urlKey !== '' && $this->urlKeyGenerator->isTaken($urlKey)) {
            $output->writeln('<error>URL key "' . $urlKey . '" is already used.</error>');

            return Command::FAILURE;
        }
        $urlKey = $urlKey ?: $this->urlKeyGenerator->generate((string) ($input->getOption(self::OPT_TITLE) ?? $name));
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $rule = $this->ruleFactory->create();
            $rule->addData([
                'name' => $name,
                'title' => (string) ($input->getOption(self::OPT_TITLE) ?? $name),
                'is_active' => $input->getOption(self::OPT_INACTIVE) ? 0 : 1,
                'priority' => (int) $input->getOption(self::OPT_PRIORITY),
                'exclude_other_discounts' => $input->getOption(self::OPT_ALLOW_OTHER) ? 0 : 1,
                'store_ids' => $storeIds,
                'url_key' => $urlKey,
                'description' => (string) $input->getOption(self::OPT_DESCRIPTION) ?: null,
            ]);
            $this->ruleResource->save($rule);

            foreach ($slots as $position => $definition) {
                $slot = $this->slotFactory->create();
                $slot->addData([
                    'rule_id' => (int) $rule->getId(),
                    'title' => $definition['title'],
                    'sort_order' => ($position + 1) * 10,
                    'slot_type' => $definition['slot_type'],
                    'sort_by' => $definition['sort_by'],
                    'use_as_entry' => $definition['use_as_entry'],
                    'discount_type' => $definition['discount_type'],
                    'discount_amount' => $definition['discount_amount'],
                ]);
                $slot->loadPost(['conditions' => $this->skuConditionBuilder->build($definition['skus'])]);
                $this->slotResource->save($slot);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $this->ruleIndexer->reindexRule((int) $rule->getId());
        $output->writeln(sprintf(
            '<info>Created bundle deal #%d "%s" with %d slots.</info>',
            (int) $rule->getId(),
            $name,
            count($slots)
        ));

        return Command::SUCCESS;
    }

    /**
     * Throws when a SKU of a slot definition does not exist.
     *
     * @param array<int,array{title:string,skus:string[]}> $slots
     * @throws \InvalidArgumentException
     */
    private function assertSkusExist(array $slots): void
    {
        $wanted = array_merge(...array_map(static fn (array $slot): array => $slot['skus'], $slots));
        $found = $this->productCollectionFactory->create()
            ->addAttributeToFilter('sku', ['in' => $wanted])
            ->getColumnValues('sku');
        $missing = array_diff(array_map('strtolower', $wanted), array_map('strtolower', $found));
        if ($missing) {
            throw new \InvalidArgumentException('Unknown SKU(s): ' . implode(', ', $missing));
        }
    }

    /**
     * Resolves comma separated store view codes to store ids.
     *
     * @return int[]
     * @throws LocalizedException
     */
    private function resolveStoreIds(string $codes): array
    {
        $storeIds = [];
        $codeList = array_filter(
            array_map('trim', explode(',', $codes)),
            static fn (string $code): bool => $code !== ''
        );
        foreach ($codeList as $code) {
            $storeIds[] = (int) $this->storeRepository->get($code)->getId();
        }

        return $storeIds ?: [0];
    }
}
