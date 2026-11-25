<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use FalconMedia\BundleDeals\Model\ResourceModel\Rule\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * All bundle deal rules as options, labelled "#id name".
 */
class Rules implements OptionSourceInterface
{
    /**
     * @var array<int, array{value: int, label: string}>|null
     */
    private ?array $options = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Returns the bundle deals as options.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [];
            $collection = $this->collectionFactory->create()->setOrder('name', 'ASC');
            foreach ($collection as $rule) {
                $this->options[] = [
                    'value' => (int) $rule->getId(),
                    'label' => sprintf('#%d %s', (int) $rule->getId(), (string) $rule->getData('name')),
                ];
            }
        }

        return $this->options;
    }
}
