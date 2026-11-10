<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Feed;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Progress of a running feed generation, per store view.
 *
 * The generation writes here while it runs; the admin polls it over a second request. The cache
 * is the right home for this: it is shared between the PHP workers that handle those two requests,
 * and a record left behind by a worker that died expires on its own.
 */
class Progress
{
    /**
     * Long enough for a full catalogue run, short enough that an abandoned record disappears.
     */
    public const LIFETIME = 3600;

    private const CACHE_PREFIX = 'tradetracker_feed_progress_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Record that a run has started and how many products it expects to write.
     */
    public function start(int $storeId, int $total): void
    {
        $this->save($storeId, [
            'total' => $total,
            'written' => 0,
            'done' => false,
            'message' => null,
            'error' => null,
        ]);
    }

    /**
     * Record how many products have been written so far.
     */
    public function advance(int $storeId, int $written): void
    {
        $state = $this->get($storeId);
        if ($state === null || $state['done']) {
            return;
        }

        $state['written'] = $written;
        $this->save($storeId, $state);
    }

    /**
     * Record that the run finished, with the result line the feed overview also shows.
     */
    public function finish(int $storeId, string $message): void
    {
        $state = $this->get($storeId) ?? ['total' => 0, 'written' => 0];
        $this->save($storeId, [
            'total' => $state['total'],
            'written' => $state['written'],
            'done' => true,
            'message' => $message,
            'error' => null,
        ]);
    }

    /**
     * Record that the run failed, so a poll that outlives the request still shows why.
     */
    public function fail(int $storeId, string $error): void
    {
        $state = $this->get($storeId) ?? ['total' => 0, 'written' => 0];
        $this->save($storeId, [
            'total' => $state['total'],
            'written' => $state['written'],
            'done' => true,
            'message' => null,
            'error' => $error,
        ]);
    }

    /**
     * The state of the run for this store view, or null when there is none.
     *
     * @return array{total: int, written: int, done: bool, message: string|null, error: string|null}|null
     */
    public function get(int $storeId): ?array
    {
        $raw = $this->cache->load($this->key($storeId));
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $state = $this->serializer->unserialize($raw);
        if (!is_array($state)) {
            return null;
        }

        return [
            'total' => (int)($state['total'] ?? 0),
            'written' => (int)($state['written'] ?? 0),
            'done' => (bool)($state['done'] ?? false),
            'message' => isset($state['message']) ? (string)$state['message'] : null,
            'error' => isset($state['error']) ? (string)$state['error'] : null,
        ];
    }

    /**
     * Drop the record, so a next poll reports that nothing is running.
     */
    public function clear(int $storeId): void
    {
        $this->cache->remove($this->key($storeId));
    }

    private function save(int $storeId, array $state): void
    {
        $this->cache->save(
            (string)$this->serializer->serialize($state),
            $this->key($storeId),
            [],
            self::LIFETIME
        );
    }

    private function key(int $storeId): string
    {
        return self::CACHE_PREFIX . $storeId;
    }
}
