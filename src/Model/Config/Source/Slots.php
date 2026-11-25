<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * All bundle deal slots as options, valued "ruleId:slotId" and labelled "#rule name / slot title".
 */
class Slots implements OptionSourceInterface
{
    /**
     * @var array<int, array{value: string, label: string}>|null
     */
    private ?array $options = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Returns the slots as options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [];
            $collection = $this->collectionFactory->create();
            $collection->getSelect()->joinInner(
                ['rule' => $collection->getTable('falconmedia_bundle_deal_rule')],
                'rule.rule_id = main_table.rule_id',
                ['rule_name' => 'rule.name']
            )->order(['rule.name ASC', 'main_table.sort_order ASC', 'main_table.slot_id ASC']);
            foreach ($collection as $slot) {
                $this->options[] = [
                    'value' => (int) $slot->getData('rule_id') . ':' . (int) $slot->getId(),
                    'label' => sprintf(
                        '#%d %s / %s',
                        (int) $slot->getData('rule_id'),
                        (string) $slot->getData('rule_name'),
                        (string) $slot->getData('title')
                    ),
                ];
            }
        }

        return $this->options;
    }
}
