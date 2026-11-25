<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Control\Rule;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * Returns the button configuration.
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $ruleId = (int) $this->request->getParam('rule_id');
        if (!$ruleId) {
            return [];
        }

        return [
            'label' => __('Delete Bundle Deal'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs(
                    (string) __('Are you sure you want to delete this bundle deal and its slots?')
                ),
                $this->urlBuilder->getUrl('bundledeals/rule/delete', ['rule_id' => $ruleId])
            ),
            'sort_order' => 20,
        ];
    }
}
