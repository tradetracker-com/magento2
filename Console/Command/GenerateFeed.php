<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Console\Command;

use Magento\Framework\App\State as AppState;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TradeTracker\Connect\Api\Feed\RepositoryInterface;
use TradeTracker\Connect\Console\CommandOptions\CreateFeedOptions;

/**
 * Command to create feed
 */
class GenerateFeed extends Command
{

    /**
     * Create feed command
     */
    public const COMMAND_NAME = 'tradetracker:feed:create';

    /**
     * @var RepositoryInterface
     */
    private $feedRepository;
    /**
     * @var AppState
     */
    private $appState;
    /**
     * @var CreateFeedOptions
     */
    private $options;

    /**
     * CreateFeed constructor.
     * @param CreateFeedOptions $options
     * @param RepositoryInterface $feedRepository
     * @param AppState $appState
     */
    public function __construct(
        CreateFeedOptions $options,
        RepositoryInterface $feedRepository,
        AppState $appState
    ) {
        $this->options = $options;
        $this->feedRepository = $feedRepository;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     *  {@inheritdoc}
     */
    public function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Generate Product Feed');
        $this->setDefinition($this->options->getOptionsList());
        parent::configure();
    }

    /**
     *  {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('store-id') === null) {
            $storeIds = [];
        } else {
            $storeIds[] = $input->getOption('store-id');
        }
        $this->feedRepository->cliProcess($output, $storeIds);
        return Cli::RETURN_SUCCESS;
    }
}
