<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\System\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\App\RequestInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Comment Class to display Direct Linking url
 */
class Comment implements CommentInterface
{

    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * Comment constructor.
     * @param RequestInterface $request
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        RequestInterface $request,
        ConfigRepository $configRepository
    ) {
        $this->request = $request;
        $this->configRepository = $configRepository;
    }

    /**
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue)
    {
        $storeId = $this->request->getParam('store');
        $store = $this->configRepository->getStore($storeId);
        $url = $store->getBaseUrl() . $elementValue;
        return (string)__(
            'Set a Url Key to be used for Direct Linking. Current saved value: "%1". <br/>Result: "%2"',
            $elementValue,
            $url
        );
    }
}
