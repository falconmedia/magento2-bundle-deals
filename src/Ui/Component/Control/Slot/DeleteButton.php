<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Control\Slot;

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
        $slotId = (int) $this->request->getParam('slot_id');
        if (!$slotId) {
            return [];
        }

        return [
            'label' => __('Delete Slot'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs((string) __('Are you sure you want to delete this slot?')),
                $this->urlBuilder->getUrl('bundledeals/slot/delete', ['slot_id' => $slotId])
            ),
            'sort_order' => 20,
        ];
    }
}
