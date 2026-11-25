<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\ViewModel;

use FalconMedia\BundleDeals\Model\Config\Source\OverviewSort;
use FalconMedia\BundleDeals\Model\Import\Amasty\ColorGroupResolver;
use FalconMedia\BundleDeals\Model\Overview\CardBuilder;
use FalconMedia\BundleDeals\Model\Overview\Config;
use FalconMedia\BundleDeals\Model\Overview\UrlBuilder;
use FalconMedia\BundleDeals\Model\Page\Config as PageConfig;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use FalconMedia\BundleDeals\Model\Storefront\ColorGroupProvider;
use FalconMedia\BundleDeals\ViewModel\Overview;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class OverviewRequestParamsTest extends TestCase
{
    /**
     * @var array<string,mixed>
     */
    private $params = [];

    /** @var string */
    private $brandParam = 'brand';

    /**
     * @var Overview
     */
    private $overview;

    protected function setUp(): void
    {
        // Array to string conversions must fail the test, as they fail the request in Magento.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(function (string $key, $default = null) {
            return $this->params[$key] ?? $default;
        });
        $config = $this->createMock(Config::class);
        $config->method('getDefaultSort')->willReturn('popular');
        $config->method('getBrandParam')->willReturnCallback(fn (): string => $this->brandParam);
        $sortSource = $this->createMock(OverviewSort::class);
        $sortSource->method('getCodes')->willReturn(['popular', 'price_asc']);

        $this->overview = new Overview(
            $config,
            $this->createMock(UrlBuilder::class),
            $this->createMock(BundleDataProvider::class),
            $this->createMock(ColorGroupProvider::class),
            $this->createMock(CardBuilder::class),
            $this->createMock(ProductAttributeValues::class),
            $this->createMock(ColorGroupResolver::class),
            $sortSource,
            $this->createMock(HttpContext::class),
            $request,
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(PriceCurrencyInterface::class),
            $this->createMock(Json::class),
            $this->createMock(PageConfig::class),
            $this->createMock(UrlInterface::class)
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testScalarParamsAreRead(): void
    {
        $this->params = ['sport' => 'Kickboksen', 'brand' => 'venum', 'sort' => 'price_asc', 'p' => '3'];

        $this->assertSame(
            [CardBuilder::FILTER_SPORT => 'kickboksen', CardBuilder::FILTER_BRAND => 'venum'],
            $this->overview->getFilters()
        );
        $this->assertSame('price_asc', $this->overview->getSort());
        $this->assertSame(3, $this->overview->getPage());
        $this->assertTrue($this->overview->hasFilterParams());
    }

    public function testConfiguredBrandParamNameIsUsed(): void
    {
        $this->brandParam = 'merk';
        $this->params = ['merk' => 'venum', 'brand' => 'other'];

        $this->assertSame([CardBuilder::FILTER_BRAND => 'venum'], $this->overview->getFilters());
    }

    public function testArrayParamsAreTreatedAsAbsent(): void
    {
        $this->params = [
            'sport' => ['x'],
            'brand' => ['x'],
            'sort' => ['price_asc'],
            'p' => ['3'],
        ];

        $this->assertSame([], $this->overview->getFilters());
        $this->assertSame('popular', $this->overview->getSort());
        $this->assertSame(1, $this->overview->getPage());
        $this->assertFalse($this->overview->hasFilterParams());
    }

    public function testOtherBrandParamNameIsIgnored(): void
    {
        $this->brandParam = 'merk';
        $this->params = ['brand' => 'venum'];

        $this->assertSame([], $this->overview->getFilters());
    }
}
