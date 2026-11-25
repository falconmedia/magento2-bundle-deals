<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Config\Source;

use FalconMedia\BundleDeals\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Options of the variation (match) attribute, for the value of a bundle deal in its variant group.
 */
class VariationOptions implements OptionSourceInterface
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var Config
     */
    private $config;

    public function __construct(EavConfig $eavConfig, Config $config)
    {
        $this->eavConfig = $eavConfig;
        $this->config = $config;
    }

    /**
     * Returns the admin labels of the variation options, with an empty option first.
     *
     * @return array<int,array{value:string, label:string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => (string) __('-- None --')]];
        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $this->config->getColorMatchAttribute());
        } catch (LocalizedException $e) {
            return $options;
        }
        if (!$attribute->getId() || !$attribute->usesSource()) {
            return $options;
        }
        $source = $attribute->getSource();
        // Only the table source takes the flag that leaves out its own empty option.
        $sourceOptions = $source instanceof Table ? $source->getAllOptions(false) : $source->getAllOptions();
        foreach ($sourceOptions as $option) {
            $options[] = ['value' => (string) $option['value'], 'label' => (string) $option['label']];
        }

        return $options;
    }
}
