<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TradeTracker\Connect\Api\Config\System\FeedInterface;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Model\Config\Backend\Cron;

/**
 * Bring configuration written by releases before 1.9.0 onto the paths this release reads.
 *
 * Three settings changed shape in 1.9.0:
 *
 *   - The Frequency field stored the word "custom" and a backend model wrote the expression to
 *     crontab/default/jobs/tradetracker_generate_feeds/schedule. The job now reads the field itself,
 *     and Magento's scheduler throws on "custom", so the stored word becomes the custom expression.
 *   - "Use Parent URL for Simples" under Grouped Products had no <config_path> and stored under
 *     tradetracker_feed/grouped/grouped_link, which the feed never read.
 *   - The pixel's Currency Code field shared its path with the "Overwrite Currency" toggle, so the
 *     toggle held the code (e.g. "EUR") and the code's own path was never written.
 *
 * Idempotent: every step only touches rows that still have the old shape.
 */
class MigrateConfigPaths implements DataPatchInterface
{

    private const OLD_GROUPED_LINK_PATH = 'tradetracker_feed/grouped/grouped_link';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $this->resolveCustomCronFrequency($connection);
        $this->moveGroupedLink($connection);
        $this->splitOverwriteCurrency($connection);

        $connection->endSetup();

        return $this;
    }

    /**
     * A stored "custom" becomes the expression from Custom Frequency of the same scope; without one
     * the row is removed so the config.xml default applies instead of an expression cron rejects.
     */
    private function resolveCustomCronFrequency(AdapterInterface $connection): void
    {
        $table = $this->table();
        $select = $connection->select()
            ->from($table, ['config_id', 'scope', 'scope_id'])
            ->where('path = ?', FeedInterface::XPATH_CRON_FREQUENCY)
            ->where('value = ?', Cron::CUSTOM);

        foreach ($connection->fetchAll($select) as $row) {
            $expression = $connection->fetchOne(
                $connection->select()
                    ->from($table, ['value'])
                    ->where('path = ?', Cron::CUSTOM_FREQUENCY_PATH)
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', (int)$row['scope_id'])
            );
            $expression = is_string($expression) ? trim($expression) : '';

            if ($expression === '' || $expression === Cron::CUSTOM) {
                $connection->delete($table, ['config_id = ?' => (int)$row['config_id']]);
                continue;
            }

            $connection->update($table, ['value' => $expression], ['config_id = ?' => (int)$row['config_id']]);
        }
    }

    /**
     * Move the grouped link setting to the path the feed reads, unless that path is already set for
     * the scope.
     */
    private function moveGroupedLink(AdapterInterface $connection): void
    {
        $table = $this->table();
        $select = $connection->select()
            ->from($table, ['config_id', 'scope', 'scope_id'])
            ->where('path = ?', self::OLD_GROUPED_LINK_PATH);

        foreach ($connection->fetchAll($select) as $row) {
            $exists = $connection->fetchOne(
                $connection->select()
                    ->from($table, ['config_id'])
                    ->where('path = ?', FeedInterface::XML_PATH_GROUPED_LINK)
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', (int)$row['scope_id'])
            );

            if ($exists) {
                $connection->delete($table, ['config_id = ?' => (int)$row['config_id']]);
                continue;
            }

            $connection->update(
                $table,
                ['path' => FeedInterface::XML_PATH_GROUPED_LINK],
                ['config_id = ?' => (int)$row['config_id']]
            );
        }
    }

    /**
     * A toggle row holding anything but 0 or 1 is a currency code the old field wrote there: the
     * code moves to its own path and the toggle becomes 1.
     */
    private function splitOverwriteCurrency(AdapterInterface $connection): void
    {
        $table = $this->table();
        $select = $connection->select()
            ->from($table, ['config_id', 'scope', 'scope_id', 'value'])
            ->where('path = ?', PixelInterface::XML_PATH_OVERWRITE_CURRENCY)
            ->where('value IS NOT NULL')
            ->where('value NOT IN (?)', ['0', '1', '']);

        foreach ($connection->fetchAll($select) as $row) {
            $connection->insertOnDuplicate(
                $table,
                [
                    'scope' => $row['scope'],
                    'scope_id' => (int)$row['scope_id'],
                    'path' => PixelInterface::XML_PATH_NON_DEFAULT_CURRENCY,
                    'value' => trim((string)$row['value']),
                ],
                ['value']
            );
            $connection->update($table, ['value' => '1'], ['config_id = ?' => (int)$row['config_id']]);
        }
    }

    private function table(): string
    {
        return $this->moduleDataSetup->getTable('core_config_data');
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
