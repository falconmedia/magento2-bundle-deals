<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Overview;

use Magento\Framework\UrlInterface;

/**
 * Builds URLs of the bundle deal overview page from its configured URL key.
 */
class UrlBuilder
{
    public const PARAM_PAGE = 'p';

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var Config
     */
    private $config;

    public function __construct(UrlInterface $url, Config $config)
    {
        $this->url = $url;
        $this->config = $config;
    }

    /**
     * Returns the overview URL with query parameters; empty values and page 1 are left out.
     *
     * @param array<string,scalar|null> $params
     */
    public function getOverviewUrl(array $params = [], ?int $storeId = null): string
    {
        $query = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            if ($key === self::PARAM_PAGE && (int) $value <= 1) {
                continue;
            }
            $query[$key] = $value;
        }
        ksort($query);
        $routeParams = ['_direct' => $this->config->getUrlKey($storeId), '_nosid' => true];
        if ($query) {
            $routeParams['_query'] = $query;
        }
        if ($storeId !== null) {
            $routeParams['_scope'] = $storeId;
        }

        return $this->url->getUrl('', $routeParams);
    }
}
