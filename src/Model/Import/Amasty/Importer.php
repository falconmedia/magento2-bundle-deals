<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Import\Amasty;

use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\Import\SkuConditionBuilder;
use FalconMedia\BundleDeals\Model\Indexer\RuleIndexer;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\ResourceModel\Rule as RuleResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory as SlotCollectionFactory;
use FalconMedia\BundleDeals\Model\RuleFactory;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Imports Amasty packs as bundle deals; one transaction per pack, matched on the external reference.
 */
class Importer
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_KEEP = 'keep';
    public const ACTION_SKIP = 'skip';

    private const STYLE_GROUP_ATTRIBUTE = 'style_group';

    /**
     * @var PackReader
     */
    private $reader;

    /**
     * @var PackMapper
     */
    private $mapper;

    /**
     * @var ColorGroupResolver
     */
    private $colorGroupResolver;

    /**
     * @var ProductAttributeValues
     */
    private $attributeValues;

    /**
     * @var SkuConditionBuilder
     */
    private $skuConditionBuilder;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var RuleResource
     */
    private $ruleResource;

    /**
     * @var SlotFactory
     */
    private $slotFactory;

    /**
     * @var SlotResource
     */
    private $slotResource;

    /**
     * @var SlotCollectionFactory
     */
    private $slotCollectionFactory;

    /**
     * @var RuleIndexer
     */
    private $ruleIndexer;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        PackReader $reader,
        PackMapper $mapper,
        ColorGroupResolver $colorGroupResolver,
        ProductAttributeValues $attributeValues,
        SkuConditionBuilder $skuConditionBuilder,
        RuleFactory $ruleFactory,
        RuleResource $ruleResource,
        SlotFactory $slotFactory,
        SlotResource $slotResource,
        SlotCollectionFactory $slotCollectionFactory,
        RuleIndexer $ruleIndexer,
        ResourceConnection $resource,
        Config $config
    ) {
        $this->config = $config;
        $this->reader = $reader;
        $this->mapper = $mapper;
        $this->colorGroupResolver = $colorGroupResolver;
        $this->attributeValues = $attributeValues;
        $this->skuConditionBuilder = $skuConditionBuilder;
        $this->ruleFactory = $ruleFactory;
        $this->ruleResource = $ruleResource;
        $this->slotFactory = $slotFactory;
        $this->slotResource = $slotResource;
        $this->slotCollectionFactory = $slotCollectionFactory;
        $this->ruleIndexer = $ruleIndexer;
        $this->resource = $resource;
    }

    /**
     * Maps all packs and resolves colour groups; the result is what an import would write.
     *
     * @return array{packs:array<int,array<string,mixed>>, groups:array<string,array<string,mixed>>}
     * @throws LocalizedException
     */
    public function plan(bool $keepStatus = false, bool $childrenAsEntry = false): array
    {
        $missing = $this->reader->getMissingTables();
        if ($missing) {
            throw new LocalizedException(
                __('The Amasty table(s) %1 do not exist; nothing to import.', implode(', ', $missing))
            );
        }

        $packs = $this->reader->getPacks();
        $products = $this->reader->getProducts($this->reader->collectProductIds($packs));
        $mapped = [];
        foreach ($packs as $packId => $pack) {
            $mapped[$packId] = $this->mapper->map($pack, $products, $keepStatus, $childrenAsEntry);
            $mapped[$packId]['name'] = (string) ($pack['name'] ?? '');
        }

        $groups = $this->resolveColorGroups($mapped);
        $colorOptions = $this->getFirstEntryColorOptions($mapped);
        foreach ($mapped as $packId => $result) {
            if ($result['status'] !== PackMapper::STATUS_OK) {
                continue;
            }
            $mapped[$packId]['rule']['color_group'] = null;
            $mapped[$packId]['rule']['color_sort_order'] = null;
            $mapped[$packId]['rule']['color_option_id'] = $colorOptions[$packId] ?? null;
        }
        foreach ($groups as $code => $group) {
            foreach ($group['members'] as $packId => $sortOrder) {
                $mapped[$packId]['rule']['color_group'] = $code;
                $mapped[$packId]['rule']['color_sort_order'] = $sortOrder;
            }
        }

        return ['packs' => $mapped, 'groups' => $groups];
    }

    /**
     * Writes one mapped pack: creates or updates the rule and replaces its slots, then reindexes it.
     *
     * Updating keeps the current status of the rule unless $keepStatus asks for the Amasty status, so a deal
     * switched off in the admin stays off.
     *
     * @param array<string,mixed> $mapped
     * @return string create or update
     * @throws \Exception
     */
    public function write(array $mapped, bool $keepStatus = false): string
    {
        $ruleId = $this->ruleResource->getIdByExternalReference((string) $mapped['rule']['external_reference']);
        $rule = $this->ruleFactory->create();
        if ($ruleId) {
            $this->ruleResource->load($rule, $ruleId);
        }
        $action = $rule->getId() ? self::ACTION_UPDATE : self::ACTION_CREATE;

        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $data = $mapped['rule'];
            if ($action === self::ACTION_UPDATE && !$keepStatus) {
                unset($data['is_active']);
            }
            $rule->addData($data);
            $this->ruleResource->save($rule);

            $existing = $this->slotCollectionFactory->create()
                ->addFieldToFilter('rule_id', ['eq' => (int) $rule->getId()]);
            foreach ($existing as $slot) {
                $this->slotResource->delete($slot);
            }
            foreach ($mapped['slots'] as $definition) {
                $slot = $this->slotFactory->create();
                $slot->addData([
                    'rule_id' => (int) $rule->getId(),
                    'title' => $definition['title'],
                    'sort_order' => $definition['sort_order'],
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
            throw $e;
        }

        $this->ruleIndexer->reindexRule((int) $rule->getId());

        return $action;
    }

    /**
     * Returns the action an import of a mapped pack would take, without writing.
     *
     * An imported rule is kept as is unless $force is set, because updating replaces admin edits and manual links.
     *
     * @param array<string,mixed> $mapped
     */
    public function getPlannedAction(array $mapped, bool $force = false): string
    {
        if ($mapped['status'] !== PackMapper::STATUS_OK) {
            return self::ACTION_SKIP;
        }

        if (!$this->ruleResource->getIdByExternalReference((string) $mapped['rule']['external_reference'])) {
            return self::ACTION_CREATE;
        }

        return $force ? self::ACTION_UPDATE : self::ACTION_KEEP;
    }

    /**
     * Resolves colour groups over all importable packs.
     *
     * @param array<int,array<string,mixed>> $mapped
     * @return array<string,array<string,mixed>>
     */
    private function resolveColorGroups(array $mapped): array
    {
        $members = [];
        $entryIds = [];
        foreach ($mapped as $packId => $result) {
            if ($result['status'] === PackMapper::STATUS_OK) {
                $members[$packId] = [
                    'name' => $result['name'] !== '' ? $result['name'] : $result['rule']['title'],
                    'entry_product_ids' => $result['entry_product_ids'],
                ];
                $entryIds[] = $result['entry_product_ids'];
            }
        }
        $entryIds = $entryIds ? array_values(array_unique(array_merge(...$entryIds))) : [];

        if ($this->attributeValues->attributeExists(self::STYLE_GROUP_ATTRIBUTE)) {
            $styles = $this->attributeValues->getValues(self::STYLE_GROUP_ATTRIBUTE, $entryIds, 0);
            foreach ($members as $packId => $member) {
                foreach ($member['entry_product_ids'] as $productId) {
                    if (isset($styles[$productId])) {
                        $members[$packId]['style_group'] = $styles[$productId];
                        break;
                    }
                }
            }
        }

        $knownColors = array_merge(
            array_values($this->attributeValues->getOptionLabels($this->config->getColorMatchAttribute(), 0)),
            array_values($this->attributeValues->getOptionLabels($this->config->getSwatchAttribute(), 0))
        );

        return $this->colorGroupResolver->resolve(
            $members,
            $this->attributeValues->getColorVariantLinks($entryIds),
            $knownColors
        );
    }

    /**
     * Returns the "color" option id of the first entry product of each pack.
     *
     * @param array<int,array<string,mixed>> $mapped
     * @return array<int,int> pack id => option id
     */
    private function getFirstEntryColorOptions(array $mapped): array
    {
        $firstEntries = [];
        foreach ($mapped as $packId => $result) {
            if ($result['status'] === PackMapper::STATUS_OK && $result['entry_product_ids']) {
                $firstEntries[$packId] = (int) $result['entry_product_ids'][0];
            }
        }
        $options = $this->attributeValues->getOptionIds($this->config->getColorMatchAttribute(), array_values($firstEntries), 0);
        $result = [];
        foreach ($firstEntries as $packId => $productId) {
            if (isset($options[$productId][0])) {
                $result[$packId] = $options[$productId][0];
            }
        }

        return $result;
    }
}
