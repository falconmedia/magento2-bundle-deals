<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RuleActions extends Column
{
    /**
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Adds the edit and delete actions to every grid row.
     *
     * @param array<string,mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource)
    {
        foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
            if (!isset($item['rule_id'])) {
                continue;
            }
            $name = $this->escaper->escapeHtml((string) ($item['name'] ?? ''));
            $dataSource['data']['items'][$key][$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('bundledeals/rule/edit', ['rule_id' => $item['rule_id']]),
                    'label' => __('Edit'),
                ],
                'add_slot' => [
                    'href' => $this->urlBuilder->getUrl('bundledeals/slot/new', ['rule_id' => $item['rule_id']]),
                    'label' => __('Add Slot'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl('bundledeals/rule/delete', ['rule_id' => $item['rule_id']]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $name),
                        'message' => __('Are you sure you want to delete the bundle deal %1?', $name),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
