<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Overview;

use FalconMedia\BundleDeals\Model\Overview\Config;
use FalconMedia\BundleDeals\ViewModel\Overview;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Bundle deal overview page, reached through the configured URL key.
 */
class Index implements HttpGetActionInterface
{
    private const ROBOTS_FILTERED = 'NOINDEX,FOLLOW';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var ForwardFactory
     */
    private $forwardFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Overview
     */
    private $overview;

    public function __construct(
        PageFactory $pageFactory,
        ForwardFactory $forwardFactory,
        Config $config,
        Overview $overview
    ) {
        $this->pageFactory = $pageFactory;
        $this->forwardFactory = $forwardFactory;
        $this->config = $config;
        $this->overview = $overview;
    }

    /**
     * Renders the overview, or the 404 page when the overview is disabled.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        /**
         * @var Page $page
         */
        $page = $this->pageFactory->create();
        $pageConfig = $page->getConfig();
        $pageConfig->getTitle()->set($this->config->getMetaTitle());
        $description = $this->config->getMetaDescription();
        if ($description !== '') {
            $pageConfig->setDescription($description);
        }
        if ($this->overview->hasFilterParams()) {
            $pageConfig->setRobots(self::ROBOTS_FILTERED);
        }
        $pageConfig->addRemotePageAsset(
            $this->overview->getCanonicalUrl(),
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );

        return $page;
    }
}
