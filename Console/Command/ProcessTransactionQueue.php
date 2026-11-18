<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Service\Transaction\ProcessQueue;

class ProcessTransactionQueue extends Command
{
    public const COMMAND_NAME = 'tradetracker:transaction:process-queue';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly ProcessQueue $processQueue,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Send the queued transaction assessments (cancellations, credit memos) to TradeTracker');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->configRepository->isEnabled()) {
            $output->writeln('<error>Module is not enabled.</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            $result = $this->processQueue->execute();
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf(
            'Processed %d: %d done, %d refused, %d failed for good, %d postponed.',
            $result['processed'],
            $result['done'],
            $result['refused'],
            $result['failed'],
            $result['postponed']
        ));

        return Cli::RETURN_SUCCESS;
    }
}
