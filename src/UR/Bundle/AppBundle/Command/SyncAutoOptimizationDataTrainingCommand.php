<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SyncAutoOptimizationDataTrainingCommand extends ContainerAwareCommand
{
    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:auto-optimization:data-training:sync')
            ->addArgument('autoOptimizationConfigId', InputArgument::REQUIRED, 'Auto Optimization Config Id')
            ->setDescription('Synchronization AutoOptimizationConfig with __auto_optimization_data_training table');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('Starting command...');

        /* get inputs */
        $dataSetId = $input->getArgument('autoOptimizationConfigId');

        if (empty($dataSetId)) {
            $this->logger->warning('Missing autoOptimizationConfigId');
            return;
        }

        /* find AutoOptimizationConfig */

        // TODO: do sync
    }
}