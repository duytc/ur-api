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
    /* dateRange value */
    const DATE_DYNAMIC_VALUE_LAST_7_DAYS = 'last 7 days';
    const DATE_DYNAMIC_VALUE_LAST_30_DAYS = 'last 30 days';
    const DATE_DYNAMIC_VALUE_THIS_MONTH = 'this month';
    const DATE_DYNAMIC_VALUE_LAST_MONTH = 'last month';
    const DATE_DYNAMIC_VALUE_LAST_2_MONTH = 'last 2 months';
    const DATE_DYNAMIC_VALUE_LAST_3_MONTH = 'last 3 months';
    const INPUT_DATA_FORCE = 'force';

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:auto-optimization:data-training:sync')
            ->addArgument('autoOptimizationConfigId', InputArgument::REQUIRED, 'Auto Optimization Config Id')
            ->addOption(self::INPUT_DATA_FORCE, 'f',InputOption::VALUE_NONE,'remove all old data')
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

        $dateRange = $autoOptimizationConfig->getDateRange();
        $startDateEndDateValue = $this->getDynamicDate($dateRange);
        $startDate = isset($startDateEndDateValue[0]) ? $startDateEndDateValue[0] : '';
        $endDate = isset($startDateEndDateValue[1]) ? $startDateEndDateValue[1] : '';

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

        /* generate final report */
        $result->generateReports();

        return $result;
    }

    /**
     * get startDate and endDate from dynamic date value
     *
     * @param string $dateValue
     * @return array as [startDate, endDate], on fail => return ['', '']
     */
    public static function getDynamicDate($dateValue)
    {
        $startDate = '';
        $endDate = '';

        if (self::DATE_DYNAMIC_VALUE_LAST_7_DAYS == $dateValue) {
            $startDate = date('Y-m-d', strtotime('-7 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_30_DAYS == $dateValue) {
            $startDate = date('Y-m-d', strtotime('-30 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
        }

        if (self::DATE_DYNAMIC_VALUE_THIS_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('this month'));
            $endDate = date('Y-m-d', strtotime('now'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('last month'));
            $endDate = date('Y-m-t', strtotime('last month'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_2_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('-2 month'));
            $endDate = date('Y-m-t', strtotime('-2 month'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_3_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('-3 month'));
            $endDate = date('Y-m-t', strtotime('-3 month'));
        }

        return [$startDate, $endDate];
    }
}