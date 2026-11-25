<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Storefront;

use FalconMedia\BundleDeals\Model\Config;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Block\Product\View\Type\ConfigurableFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Storefront data of bundle deal products: price after catalog rules, image and configurable options.
 */
class ProductDataBuilder
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConfigurableFactory $configurableBlockFactory,
        private readonly ImageFactory $imageFactory,
        private readonly Visibility $visibility,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    /**
     * Salable, visible products in the given order.
     *
     * @param int[] $productIds
     * @return Product[] keyed by product id
     */
    public function loadProducts(array $productIds, int $storeId): array
    {
        if (!$productIds) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addIdFilter($productIds)
            ->addAttributeToSelect([
                'name',
                'thumbnail',
                'small_image',
                'url_key',
                'price',
                'special_price',
                $this->config->getColorMatchAttribute($storeId),
            ])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->setVisibility($this->visibility->getVisibleInSiteIds())
            ->addUrlRewrite();

        $loaded = [];
        /**
         * @var Product $product
         */
        foreach ($collection as $product) {
            if ($product->isSalable()) {
                $loaded[(int) $product->getId()] = $product;
            }
        }

        $ordered = [];
        foreach ($productIds as $productId) {
            if (isset($loaded[(int) $productId])) {
                $ordered[(int) $productId] = $loaded[(int) $productId];
            }
        }

        return $ordered;
    }

    /**
     * Builds the storefront data of one product.
     *
     * @param bool $withOptions include the configurable options; overview cards do not need them
     * @return array<string,mixed>
     */
    public function build(Product $product, bool $withOptions = true): array
    {
        $storeId = (int) $product->getStoreId();
        $data = [
            'productId' => (int) $product->getId(),
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'url' => (string) $product->getProductUrl(),
            'image' => (string) $this->imageFactory->create()->init($product, $this->imageId($product))->getUrl(),
            'colour' => $this->colourLabel($product, $storeId),
            'imageLarge' => $this->largeImageUrl($product),
            'imageCard' => $this->largeImageUrl($product, 'catalog_bundledeal_thumbnail'),
            'finalPrice' => (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(),
            'regularPrice' => (float) $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue(),
            'type' => (string) $product->getTypeId(),
            'options' => null,
        ];

        if ($withOptions && $product->getTypeId() === Configurable::TYPE_CODE) {
            $data['options'] = $this->getConfigurableOptions($product);
        }

        return $data;
    }

    /**
     * Subset of the native configurable jsonConfig the bundle component needs.
     *
     * @return array<string,mixed>
     */
    private function getConfigurableOptions(Product $product): array
    {
        $block = $this->configurableBlockFactory->create();
        $block->setProduct($product);
        $config = $this->json->unserialize($block->getJsonConfig());

        $attributes = [];
        foreach ((array) ($config['attributes'] ?? []) as $attribute) {
            $options = [];
            foreach ((array) ($attribute['options'] ?? []) as $option) {
                if (empty($option['products'])) {
                    continue;
                }
                $options[] = [
                    'id' => (string) $option['id'],
                    'label' => (string) $option['label'],
                    'products' => array_map('strval', (array) $option['products']),
                ];
            }
            $attributes[] = [
                'id' => (string) $attribute['id'],
                'code' => (string) $attribute['code'],
                'label' => (string) $attribute['label'],
                'options' => $options,
            ];
        }

        $prices = [];
        foreach ((array) ($config['optionPrices'] ?? []) as $childId => $price) {
            $prices[(string) $childId] = [
                'finalPrice' => (float) ($price['finalPrice']['amount'] ?? 0),
                'regularPrice' => (float) ($price['oldPrice']['amount'] ?? 0),
            ];
        }

        return [
            'attributes' => $attributes,
            'index' => (object) ($config['index'] ?? []),
            'prices' => (object) $prices,
        ];
    }

    /**
     * Resizes the best available image role to the page size; products without a base image only have thumbnails.
     */
    private function largeImageUrl(Product $product, string $imageId = 'product_page_image_medium'): string
    {
        $helper = $this->imageFactory->create()->init($product, $imageId);
        foreach (['image', 'small_image', 'thumbnail'] as $role) {
            $file = (string) $product->getData($role);
            if ($file !== '' && $file !== 'no_selection') {
                $helper->setImageFile($file);
                break;
            }
        }

        return (string) $helper->getUrl();
    }

    /**
     * Colour option label, falling back to the part of the name after the last dash; empty when the name has none.
     */
    private function colourLabel(Product $product, int $storeId): string
    {
        $code = $this->config->getColorMatchAttribute($storeId);
        // An unknown attribute code in the admin must not break the product page.
        /**
         * @var \Magento\Catalog\Model\ResourceModel\Product $resource
         */
        $resource = $product->getResource();
        $text = $resource->getAttribute($code) ? $product->getAttributeText($code) : '';
        $parts = array_map('trim', explode(',', is_array($text) ? implode(',', $text) : (string) $text));
        $label = implode(', ', array_filter(array_unique($parts), static fn (string $part): bool => $part !== ''));
        if ($label !== '') {
            return $label;
        }
        $parts = preg_split('/\s+[-\x{2013}]\s+|[-\x{2013}]\s*(?=\p{L})/u', (string) $product->getName());

        return is_array($parts) && count($parts) > 1 ? trim((string) end($parts)) : '';
    }

    /**
     * Uses the thumbnail, or the small image when a store view clears the thumbnail.
     */
    private function imageId(Product $product): string
    {
        $thumbnail = (string) $product->getData('thumbnail');

        return $thumbnail !== '' && $thumbnail !== 'no_selection' ? 'product_thumbnail_image' : 'product_small_image';
    }
}
