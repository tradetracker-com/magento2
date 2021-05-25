<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Api\Feed;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Feed repository interface
 */
interface RepositoryInterface
{

    const DEFAULT_DIRECTORY = 'tradetracker';
    const DEFAULT_DIRECTORY_PATH = 'pub/media/tradetracker';

    const PREVIEW_URL = 'tradetracker/feed/preview';
    const DOWNLOAD_URL = 'tradetracker/feed/download';
    const GENERATE_URL = 'tradetracker/feed/generate';

    /**
     * Returns feed configuration data array for all stores
     *
     * @return array
     */
    public function getStoreData(): array;

    /**
     * Returns feed location data array for store
     *
     * @param int $storeId
     * @param null $type
     * @return array
     */
    public function getFeedLocation(int $storeId, $type = null): array;

    /**
     * Generate feed and write to file
     *
     * @param int $storeId
     * @param string $type
     * @return array
     */
    public function generateAndSaveFeed(int $storeId, string $type = 'manual'): array;

    /**
     * @param OutputInterface $output
     * @param array $storeIds
     * @return void
     */
    public function cliProcess(OutputInterface $output, array $storeIds = []): void;
}
