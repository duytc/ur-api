<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\AutoOptimizationConfigManagerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingCollectorInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;
use UR\Service\DTO\Report\ReportResultInterface;

class SyncAutoOptimizationDataTrainingCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:auto-optimization:data-training:sync';
    const INPUT_DATA_FORCE = 'force';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('autoOptimizationConfigId', InputArgument::REQUIRED, 'Auto Optimization Config Id')
            ->addOption(self::INPUT_DATA_FORCE, 'f', InputOption::VALUE_NONE, 'Remove all old data')
            ->setDescription('Synchronization AutoOptimizationConfig with __auto_optimization_data_training table');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        $io->section('Starting command...');

        /* get inputs */
        $autoOptimizationConfigId = $input->getArgument('autoOptimizationConfigId');
        $forceRun = $input->getOption('force');

        if (!empty($forceRun)) {
            $removeOldData = true;
        } else {
            $removeOldData = false;
        }
        if (empty($autoOptimizationConfigId)) {
            $io->warning('Missing autoOptimizationConfigId');
            return;
        }

        /* find AutoOptimizationConfig */
        /** @var AutoOptimizationConfigManagerInterface $autoOptimizationConfigManager */
        $autoOptimizationConfigManager = $container->get('ur.domain_manager.auto_optimization_config');

        /** @var AutoOptimizationConfigInterface $autoOptimizationConfig */
        $autoOptimizationConfig = $autoOptimizationConfigManager->find($autoOptimizationConfigId);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            $io->warning(sprintf('AutoOptimizationConfig #%d not found', $autoOptimizationConfigId));
            return;
        }

        /* get data training for autoOptimizationConfig */
        /** @var DataTrainingCollectorInterface $dataTrainingCollector */
        $dataTrainingCollector = $container->get('ur.service.auto_optimization.data_training_collector');
        $data = $dataTrainingCollector->buildDataForAutoOptimizationConfig($autoOptimizationConfig);
        if (!$data instanceof ReportResultInterface) {
            $io->warning(sprintf('Can not get data for AutoOptimizationConfig #%d', $autoOptimizationConfigId));
            return;
        }

        /** @var DataTrainingTableService $autoOptimizationSyncService */
        $autoOptimizationSyncService = $container->get('ur.service.auto_optimization.data_training_table_service');

        $autoOptimizationSyncService->deleteDataTrainingTable($autoOptimizationConfigId);
        $autoOptimizationSyncService->createEmptyDataTrainingTable($autoOptimizationConfig);
        $autoOptimizationSyncService->importDataToDataTrainingTable($data, $autoOptimizationConfig, $removeOldData);
        $io->success(sprintf('Finish sync data for AutoOptimizationConfig #%d', $autoOptimizationConfigId));
    }
}