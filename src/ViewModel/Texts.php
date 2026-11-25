<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Storefront texts that are editable under Stores > Configuration > Catalog > Bundle Deals > Storefront texts.
 * An empty setting falls back to the translated source string.
 */
class Texts implements ArgumentInterface
{
    private const XML_PATH_PREFIX = 'falconmedia_bundle_deals/texts/';

    /**
     * @param object[] $uspProviders Objects with getUsps(): string[], registered by other modules through di.xml
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly array $uspProviders = []
    ) {
    }

    /**
     * Returns the text for a setting, with %1, %2 replaced by the arguments.
     *
     * @param array<int,string|int|float> $arguments
     */
    public function get(string $key, array $arguments = []): Phrase
    {
        $value = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $key,
            ScopeInterface::SCOPE_STORE
        ));

        // A configured text is passed through the translator so an English default is shown translated.
        return $value !== '' ? __($value, ...$arguments) : $this->getFallback($key, $arguments);
    }

    /**
     * Built-in translated text per setting, used when the setting is empty.
     *
     * @param array<int,string|int|float> $arguments
     */
    private function getFallback(string $key, array $arguments): Phrase
    {
        return match ($key) {
            'set_added' => __('The set has been added to your shopping cart.'),
            'set_explanation' => __(
                'Every item comes as its own line in your cart, so you can still change or return items one by one.'
                . ' The set discount applies while the set is complete.'
            ),
            'product_all_direct' => __('Everything delivered tomorrow'),
            'product_complete_in' => __('Complete in %1 working days', ...$arguments),
            'product_complete_in_range' => __('Complete in %1 to %2 working days', ...$arguments),
            'product_split_shipment' => __('Delivered in 2 shipments'),
            'product_discount_in_cart' => __('The discount is applied in the shopping cart.'),
            'overview_empty_title' => __('No sets for this combination'),
            'overview_empty_hint' => __('Try another sport or brand.'),
            'overview_up_to' => __('Up to %1% off the set', ...$arguments),
            'cart_title' => __('Frequently bought together'),
            'cart_hint' => __('complete the set and get the bundle discount.'),
            default => __($key, ...$arguments),
        };
    }

    /**
     * Returns the USP lines of the set page: from the first USP provider, else the built-in texts.
     *
     * @return string[]
     */
    public function getUsps(): array
    {
        foreach ($this->uspProviders as $provider) {
            return $provider->getUsps();
        }

        return [
            (string) __('Free shipping from the configured amount'),
            (string) __('Secure payment'),
            (string) __('Questions? Our team in the shop helps you'),
        ];
    }
}
