<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\DomainManager\AutoOptimizationConfigManagerInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\Report\ParamsBuilder;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\ReportBuilderInterface;

class SyncAutoOptimizationDataTrainingCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:auto-optimization:data-training:sync';
    const INPUT_DATA_FORCE = 'force';

    /** @var Logger */
    private $logger;

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
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('Starting command...');

        /* get inputs */
        $autoOptimizationConfigId = $input->getArgument('autoOptimizationConfigId');
        $forceRun = $input->getOption('force');

        if (!empty($forceRun)) {
            $removeOldData = true;
        } else {
            $removeOldData = false;
        }
        if (empty($autoOptimizationConfigId)) {
            $this->logger->warning('Missing autoOptimizationConfigId');
            return;
        }

        /* find AutoOptimizationConfig */
        /** @var AutoOptimizationConfigManagerInterface $autoOptimizationConfigManager */
        $autoOptimizationConfigManager = $container->get('ur.domain_manager.auto_optimization_config');

        /** @var AutoOptimizationConfigInterface $autoOptimizationConfig */
        $autoOptimizationConfig = $autoOptimizationConfigManager->find($autoOptimizationConfigId);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            $this->logger->warning(sprintf('AutoOptimizationConfig #%d not found', $autoOptimizationConfigId));
            return;
        }

        /* get data training for autoOptimizationConfig */
        /** @var ReportResultInterface $data */
        $data = $this->getDataForAutoOptimizationConfig($autoOptimizationConfig);

        /** @var DataTrainingTableService $autoOptimizationSyncService */
        $autoOptimizationSyncService = $container->get('ur.service.auto_optimization.data_training_table_service');
        $autoOptimizationSyncService->importDataToDataTrainingTable($data, $autoOptimizationConfig, $removeOldData);
    }

    /**
     * getDataForAutoOptimizationConfig
     * Similar to action '/reportview/data' in UR/Bundle/ReportApiBundle/Controller/ReportController.php
     *
     * @param $autoOptimizationConfig
     * @return ReportResultInterface
     */
    private function getDataForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        /* build request params */
        /** @var Collection|AutoOptimizationConfigDataSetInterface[] $dataSets */
        $autoOptimizationConfigDataSets = $autoOptimizationConfig->getAutoOptimizationConfigDataSets();
        if ($autoOptimizationConfigDataSets instanceof Collection) {
            $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets->toArray();
        }

        $dataSets = array_map(function ($a) {
            /** @var AutoOptimizationConfigDataSetInterface $a */
            return $a->toArray();
        }, $autoOptimizationConfigDataSets);

        $dateFilter = new DateFilter();
        $dateRange = $dateFilter->getDynamicDate(DateFilter::DATE_TYPE_DYNAMIC, $autoOptimizationConfig->getDateRange());
        $startDate = reset($dateRange);
        $endDate = end($dateRange);

        $requestParams = [
            ParamsBuilder::DATA_SET_KEY => $dataSets,
            ParamsBuilder::DIMENSIONS_KEY => $autoOptimizationConfig->getDimensions(),
            ParamsBuilder::METRICS_KEY => $autoOptimizationConfig->getMetrics(),
            ParamsBuilder::FIELD_TYPES_KEY => $autoOptimizationConfig->getFieldTypes(),
            ParamsBuilder::FILTERS_KEY => $autoOptimizationConfig->getFilters(),
            ParamsBuilder::JOIN_BY_KEY => $autoOptimizationConfig->getJoinBy(),
            ParamsBuilder::TRANSFORM_KEY => $autoOptimizationConfig->getTransforms(),
            ParamsBuilder::START_DATE => $startDate,
            ParamsBuilder::END_DATE => $endDate,
        ];

        /* create params object */
        /** @var ParamsBuilderInterface $paramsBuilder */
        $paramsBuilder = $container->get('ur.services.report.params_builder');
        $params = $paramsBuilder->buildFromArray($requestParams);

        /* get report */
        /** @var ReportBuilderInterface $reportBuilder */
        $reportBuilder = $container->get('ur.services.report.report_builder');
        $result = $reportBuilder->getReport($params);

        return $result;
    }
}