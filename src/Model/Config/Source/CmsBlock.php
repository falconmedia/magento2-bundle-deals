<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * CMS blocks by identifier, with an empty option first.
 */
class CmsBlock implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory)
    {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Returns the CMS blocks as options.
     *
     * @return array<int,array{value:string, label:mixed}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- None --')]];
        $collection = $this->collectionFactory->create()->setOrder('title', 'ASC');
        foreach ($collection as $block) {
            $options[] = [
                'value' => (string) $block->getIdentifier(),
                'label' => sprintf('%s (%s)', $block->getTitle(), $block->getIdentifier()),
            ];
        }

        return $options;
    }
}
