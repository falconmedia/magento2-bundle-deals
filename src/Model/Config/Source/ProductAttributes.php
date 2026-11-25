<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes that hold options (dropdown, multiple select), for choosing the colour attribute in the admin.
 */
class ProductAttributes implements OptionSourceInterface
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
     * Returns the attribute codes with their admin labels, with an empty option first.
     *
     * @return array<int,array{value:string, label:string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => (string) __('-- Default --')]];
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('frontend_input', ['in' => ['select', 'multiselect']])
            ->setOrder('frontend_label', 'ASC');
        foreach ($collection as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            $label = trim((string) $attribute->getFrontendLabel());
            $options[] = ['value' => $code, 'label' => ($label !== '' ? $label . ' ' : '') . '(' . $code . ')'];
        }

        return $options;
    }
}
