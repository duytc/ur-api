<?php

namespace UR\Bundle\AppBundle\Command;

use DateTime;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceIntegrationBackfillHistoryManagerInterface;
use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class BackFillScheduleCommand extends ContainerAwareCommand
{
    const SUPPORTED_DATE_FORMATS = [
        'Y-m-d',
        'Y/m/d',  // 2016/01/15
        'Y-M-d',  // 2016-Mar-01
        'Y/M/d',  // 2016/Mar/01
    ];

    const COMMAND_NAME = 'ur:backfill:create';
    const INPUT_DATA_SOURCES = 'dataSources';
    const INPUT_START_DATE = 'startDate';
    const INPUT_END_DATE = 'endDate';

    /** @var  Logger */
    private $logger;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    /** @var  DataSourceIntegrationManagerInterface */
    private $dataSourceIntegrationManager;

    /** @var  DataSourceIntegrationBackfillHistoryManagerInterface */
    private $backFillHistoryManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::INPUT_DATA_SOURCES, 'd', InputOption::VALUE_REQUIRED,
                'Schedule back fill for many data source, input with ids as array without space. Example 15,17,3,9')
            ->addOption(self::INPUT_START_DATE, 'r', InputOption::VALUE_REQUIRED,
                'Back fill startDate, example 2015-04-13 or 2016-Mar-01')
            ->addOption(self::INPUT_END_DATE, 'u', InputOption::VALUE_REQUIRED,
                'Back fill endDate, example 2015-05-22 or 2016-Mar-01')
            ->setDescription('Create new back fill schedule for list data sources');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->dataSourceIntegrationManager = $container->get('ur.domain_manager.data_source_integration');
        $this->backFillHistoryManager = $container->get('ur.domain_manager.data_source_integration_backfill_history');

        if (!$this->validateInput($input, $output)) {
            $this->logger->notice('Quit command');
            return 0;
        }
        $count = 0;

        $this->logger->info('starting command...');

        $dataSources = $this->getDataSourcesFromInput($input);
        $startDate = $this->getStartDateFromInput($input);
        $endDate = $this->getEndDateFromInput($input);

        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $dataSourceIntegrations = $this->dataSourceIntegrationManager->findByDataSource($dataSource->getId());
            if (!is_array($dataSourceIntegrations)) {
                continue;
            }

            foreach ($dataSourceIntegrations as $dataSourceIntegration) {
                if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
                    continue;
                }
                if ($this->updateBackFillForDataSourceIntegration($dataSourceIntegration, $startDate, $endDate)) {
                    $count++;
                }
            }
        }
        $output->writeln(sprintf('command run successfully %s integration updated', $count));

        return 1;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        $rawDataSources = $input->getOption(self::INPUT_DATA_SOURCES);
        if (!$rawDataSources) {
            $this->logger->info('None valid data sources from input. Please check your list data source id');
            return false;
        }

        $dataSourceIds = explode(',', $rawDataSources);
        foreach ($dataSourceIds as $key => &$dataSourceId) {
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface) {
                $this->logger->info('Can not find data source with id ' . $dataSourceId);
                unset($dataSourceIds[$key]);
            }
        }

        if (count($dataSourceIds) < 1) {
            $this->logger->info('None valid data sources from input. Please check your list data source id');
            return false;
        }

        $startDate = $this->getDateFromText($input->getOption(self::INPUT_START_DATE));
        if (!$startDate instanceof \DateTime) {
            $this->logger->info('Invalid startDate from input. We accept some popular format as 2015/06/17, 2015-06-17');
            return false;
        }

        $endDate = $this->getDateFromText($input->getOption(self::INPUT_END_DATE));
        if (!$endDate instanceof \DateTime) {
            $this->logger->info('Invalid endDate from input. We accept some popular format as 2015/06/17, 2015-06-17');
            return false;
        }

        if ($startDate > $endDate) {
            $this->logger->info('Do not set endDate before startDate. Please recheck your input');
            return false;
        }

        return true;
    }

    /**
     * @param InputInterface $input
     * @return DataSourceInterface[]
     */
    private function getDataSourcesFromInput($input)
    {
        $dataSources = [];
        $dataSourceIds = explode(',', $input->getOption(self::INPUT_DATA_SOURCES));
        foreach ($dataSourceIds as $key => &$dataSourceId) {
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }
            $dataSources[] = $dataSource;
        }

        return $dataSources;
    }

    /**
     * @param InputInterface $input
     * @return \DateTime
     */
    private function getStartDateFromInput($input)
    {
        return $this->getDateFromText($input->getOption(self::INPUT_START_DATE));
    }

    /**
     * @param InputInterface $input
     * @return \DateTime
     */
    private function getEndDateFromInput($input)
    {
        $endDate = $this->getDateFromText($input->getOption(self::INPUT_END_DATE));

        return $endDate;
    }

    /**
     * @param $dataSourceIntegration
     * @param $startDate
     * @param $endDate
     * @return bool
     */
    private function updateBackFillForDataSourceIntegration($dataSourceIntegration, $startDate, $endDate)
    {
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface ||
            !$startDate instanceof \DateTime ||
            !$endDate instanceof \DateTime
        ) {
            return false;
        }

        $backFillHistory = new DataSourceIntegrationBackfillHistory();
        $backFillHistory->setDataSourceIntegration($dataSourceIntegration);
        $backFillHistory->setBackFillStartDate($startDate);
        $backFillHistory->setBackFillEndDate($endDate);

        $this->logger->info(
            sprintf('Create new back fill history, data source %s (id = %s), startDate %s, endDate %s',
                $dataSourceIntegration->getDataSource()->getName(),
                $dataSourceIntegration->getDataSource()->getId(),
                $startDate->format(DateFormat::DEFAULT_DATE_FORMAT),
                $endDate->format(DateFormat::DEFAULT_DATE_FORMAT)
            ));

        $this->backFillHistoryManager->save($backFillHistory);

        return true;
    }

    /**
     * @param $value
     * @return DateTime
     */
    public function getDateFromText($value)
    {
        foreach (self::SUPPORTED_DATE_FORMATS as $format) {
            $date = date_create_from_format($format, $value);
            if ($date instanceof DateTime) {
                return $date;
            }
        }

        return null;
    }
}