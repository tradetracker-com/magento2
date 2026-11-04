<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Model\Config\Repository;

/**
 * Encrypt passphrases that were stored as plain text by releases before the field became an encrypted field.
 *
 * Runs for every scope (default, websites, stores) and is idempotent: values that are already in Magento's
 * encrypted format are skipped.
 */
class EncryptPassphrase implements DataPatchInterface
{

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * EncryptPassphrase constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EncryptorInterface $encryptor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $connection->startSetup();

        $select = $connection->select()
            ->from($table, ['config_id', 'value'])
            ->where('path = ?', ConfigRepository::XML_PATH_PASSPHRASE)
            ->where('value IS NOT NULL')
            ->where('value != ?', '');

        foreach ($connection->fetchAll($select) as $row) {
            $value = (string)$row['value'];
            if (Repository::isEncryptedValue($value)) {
                continue;
            }
            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int)$row['config_id']]
            );
        }

        $connection->endSetup();

        return $this;
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
