<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Feed;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use TradeTracker\Connect\Api\Config\System\FeedInterface as FeedConfigRepository;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Api\ProductData\RepositoryInterface as ProductDataRepository;
use TradeTracker\Connect\Service\Feed\Create as FeedService;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Feed Repository class
 */
class Repository implements FeedRepository
{

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var FeedConfigRepository
     */
    private $feedConfigRepository;
    /**
     * @var DateTime
     */
    private $datetime;
    /**
     * @var SerializerJson
     */
    private $serializerJson;
    /**
     * @var FeedService
     */
    private $feedService;
    /**
     * @var LogRepository
     */
    private $logRepository;
    /**
     * @var UrlInterface
     */
    private $urlInterface;
    /**
     * @var ProductDataRepository
     */
    private $productDataRepository;
    /**
     * @var File
     */
    private $fileDriver;
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Feed constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param DateTime $datetime
     * @param FeedConfigRepository $feedConfigRepository
     * @param SerializerJson $serializerJson
     * @param FeedService $feedService
     * @param LogRepository $logRepository
     * @param UrlInterface $urlInterface
     * @param ProductDataRepository $productDataRepository
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        DateTime $datetime,
        FeedConfigRepository $feedConfigRepository,
        SerializerJson $serializerJson,
        FeedService $feedService,
        LogRepository $logRepository,
        UrlInterface $urlInterface,
        ProductDataRepository $productDataRepository,
        DirectoryList $directoryList,
        File $fileDriver
    ) {
        $this->feedConfigRepository = $feedConfigRepository;
        $this->datetime = $datetime;
        $this->storeManager = $storeManager;
        $this->serializerJson = $serializerJson;
        $this->feedService = $feedService;
        $this->logRepository = $logRepository;
        $this->urlInterface = $urlInterface;
        $this->productDataRepository = $productDataRepository;
        $this->fileDriver = $fileDriver;
        $this->directoryList = $directoryList;
    }

    /**
     * {@inheritDoc}
     */
    public function getStoreData(): array
    {
        $feedData = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeId = (int)$store->getStoreId();
            try {
                $feedData[$storeId] = [
                        'store_id' => $storeId,
                        'code' => $store->getCode(),
                        'name' => $store->getName(),
                        'is_active' => $store->getIsActive(),
                        'status' => $this->feedConfigRepository->isEnabled($storeId),
                        'result' => $this->feedConfigRepository->getFeedGenerationResult($storeId),
                        'preview_url' => $this->getUrl(self::PREVIEW_URL, ['store_id' => $storeId]),
                        'generate_url' => $this->getUrl(self::GENERATE_URL, ['store_id' => $storeId]),
                        'download_url' => $this->getUrl(self::DOWNLOAD_URL, ['store_id' => $storeId]),
                    ] + $this->getFeedLocation($storeId);
            } catch (\Exception $e) {
                $this->logRepository->addErrorLog('LocalizedException', $e->getMessage());
                continue;
            }
        }
        return $feedData;
    }

    /**
     * Build url by requested path and parameters
     *
     * @param string|null $routePath
     * @param array|null $routeParams
     * @return string
     */
    private function getUrl($routePath = null, $routeParams = null): string
    {
        return (string)$this->urlInterface->getUrl($routePath, $routeParams);
    }

    /**
     * {@inheritDoc}
     */
    public function getFeedLocation(int $storeId, $type = null): array
    {
        $filename = $this->feedConfigRepository->getFilename((int)$storeId);
        if ($type == 'preview') {
            $filename = str_replace('.xml', '-preview.xml', $filename);
        }

        $url = sprintf(
            '%s%s/%s',
            $this->feedConfigRepository->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            self::DEFAULT_DIRECTORY,
            $filename
        );

        $path = sprintf(
            '%s/%s/%s',
            $this->directoryList->getPath(DirectoryList::ROOT),
            self::DEFAULT_DIRECTORY_PATH,
            $filename
        );

        return [
            'url' => $url,
            'path' => $path,
            'available' => $this->isExists($path),
        ];
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isExists(string $path): bool
    {
        try {
            return $this->fileDriver->isExists($path);
        } catch (FileSystemException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function generateAndSaveFeed(int $storeId, $type = 'manual'): array
    {
        $timeStart = microtime(true);

        if (!$this->feedConfigRepository->isEnabled($storeId)) {
            return [
                'success' => false,
                'message' => __('Product Feed for this StoreId not enabled.')
            ];
        }

        try {
            $feed = [
                'productFeed' => $this->productDataRepository->getProductData($storeId, [], $type)
            ];
        } catch (\Exception $e) {
            $this->logRepository->addErrorLog('generateAndSaveFeed', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        $location = $this->getFeedLocation($storeId, $type);
        $this->feedService->execute($feed, $storeId, $location['path']);

        $resultMsg = sprintf(
            'Products in feed: %s, generated in %s on %s (%s)',
            count($feed['productFeed']),
            $this->getTimeUsage($timeStart),
            $this->datetime->gmtDate(),
            $type
        );

        $this->feedConfigRepository->setFeedGenerationResult($storeId, $resultMsg);
        $this->logRepository->addDebugLog('FeedGeneration', $resultMsg);

        return [
            'success' => true,
            'message' => sprintf('Store ID %s: %s', $storeId, $resultMsg),
            'path' => $location['path']
        ];
    }

    /**
     * @param float $timeStart
     * @return string
     */
    private function getTimeUsage(float $timeStart): string
    {
        $time = round((microtime(true) - $timeStart));
        if ($time > 120) {
            $time = round($time / 60, 1) . ' ' . __('minute(s)')->render();
        } else {
            $time = round($time) . ' ' . __('second(s)')->render();
        }

        return (string)$time;
    }

    /**
     * @inheritDoc
     */
    public function cliProcess(OutputInterface $output, array $storeIds = []): void
    {

        if (empty($storeIds)) {
            try {
                $storeIds = $this->feedConfigRepository->getAllEnabledStoreIds();
            } catch (\DomainException $exception) {
                return;
            }
        }
        foreach ($storeIds as $storeId) {
            $result = $this->generateAndSaveFeed($storeId, 'CLI');
            if ($result['success']) {
                $output->writeln(sprintf('<info>%s</info>', $result['message']));
            } else {
                $output->writeln(sprintf('<error>%s</error>', $result['message']));
            }
        }
    }
}
