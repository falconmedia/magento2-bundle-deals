<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Set;

use FalconMedia\BundleDeals\ViewModel\SetPage;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Set page of one bundle deal, reached through /<prefix>/<url key>.
 */
class View implements HttpGetActionInterface
{
    private const ROBOTS_NOINDEX = 'NOINDEX,FOLLOW';

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly SetPage $setPage
    ) {
    }

    /**
     * Renders the set page, or the 404 page when the deal cannot be served.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        if (!$this->setPage->isAvailable()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        /**
         * @var Page $page
         */
        $page = $this->pageFactory->create();
        $config = $page->getConfig();
        $config->getTitle()->set($this->setPage->getTitle());
        $config->setDescription($this->setPage->getMetaDescription());
        $config->setRobots($this->setPage->isIndexable() ? 'INDEX,FOLLOW' : self::ROBOTS_NOINDEX);
        $config->addRemotePageAsset(
            $this->setPage->getCanonicalUrl(),
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );

        return $page;
    }
}
