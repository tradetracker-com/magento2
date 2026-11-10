<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\DirectLinking;

use Magento\Framework\App\ResourceConnection;

/**
 * Service class for attribute data
 */
class UrlRewrite
{

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * UrlRewrite constructor.
     *
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    private const ENTITY_TYPE = 'trade_tracker';
    private const TARGET_PATH = 'tradetracker/redirect';

    /**
     * Save the rewrite for this key and store view, and drop the ones this module wrote for the
     * same store view under another key, so a changed Url Key does not leave the old one working.
     *
     * @param array $data
     */
    public function execute(array $data): void
    {
        $this->setRewrite($data);
        $this->removeOthers((string)$data['request_path'], (int)$data['store_id']);
    }

    private function removeOthers(string $requestPath, int $storeId): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName('url_rewrite'),
            [
                'entity_type = ?' => self::ENTITY_TYPE,
                'target_path = ?' => self::TARGET_PATH,
                'store_id = ?' => $storeId,
                'request_path != ?' => $requestPath,
            ]
        );
    }

    /**
     * @param array $data
     */
    private function setRewrite(array $data): void
    {
        $rewriteId = $this->checkRewrite((string)$data['request_path'], (int)$data['store_id']);
        if ($rewriteId) {
            $this->resource->getConnection()->update(
                $this->resource->getTableName('url_rewrite'),
                $data,
                ['url_rewrite_id = ?' => $rewriteId]
            );
        } else {
            $this->resource->getConnection()
                ->insert($this->resource->getTableName('url_rewrite'), $data);
        }
    }

    /**
     * @param string $requestPath
     * @param int $storeId
     * @return string
     */
    private function checkRewrite(string $requestPath, int $storeId): string
    {
        $selectRewrite = $this->resource->getConnection()
            ->select()
            ->from(
                ['url_rewrite' => $this->resource->getTableName('url_rewrite')],
                ['url_rewrite_id']
            )->where('request_path = ?', $requestPath)
            ->where('store_id = ?', $storeId);
        return (string)$this->resource->getConnection()->fetchOne($selectRewrite);
    }
}
