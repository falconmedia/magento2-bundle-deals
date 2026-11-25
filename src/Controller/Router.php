<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller;

use FalconMedia\BundleDeals\Model\Overview\Config;
use FalconMedia\BundleDeals\Model\Page\Config as PageConfig;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Routes the configured overview URL key (for example /bundles) to the overview controller.
 */
class Router implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    private $actionFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PageConfig
     */
    private $pageConfig;

    public function __construct(
        ActionFactory $actionFactory,
        Config $config,
        StoreManagerInterface $storeManager,
        PageConfig $pageConfig
    ) {
        $this->pageConfig = $pageConfig;
        $this->actionFactory = $actionFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Forwards the overview URL key, with or without trailing slash, to bundledeals/overview/index.
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest || $request->getModuleName() === 'bundledeals') {
            return null;
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '') {
            return null;
        }
        if (strcasecmp($path, $this->config->getUrlKey($storeId)) !== 0) {
            return $this->matchSetPage($request, $path, $storeId);
        }

        $request->setModuleName('bundledeals')
            ->setControllerName('overview')
            ->setActionName('index');
        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $path);

        return $this->actionFactory->create(Forward::class);
    }

    /**
     * Forwards /<set prefix>/<url key> to bundledeals/set/view; the controller decides on 404.
     */
    private function matchSetPage(HttpRequest $request, string $path, int $storeId): ?ActionInterface
    {
        $prefix = $this->pageConfig->getUrlPrefix($storeId) . '/';
        if (strncasecmp($path, $prefix, strlen($prefix)) !== 0) {
            return null;
        }
        $key = substr($path, strlen($prefix));
        if ($key === '' || str_contains($key, '/')) {
            return null;
        }

        $request->setModuleName('bundledeals')
            ->setControllerName('set')
            ->setActionName('view')
            ->setParam('set_key', strtolower($key));
        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $path);

        return $this->actionFactory->create(Forward::class);
    }
}
