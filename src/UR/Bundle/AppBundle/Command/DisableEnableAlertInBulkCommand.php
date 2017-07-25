<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;
use UR\Service\Alert\DataSource\AbstractDataSourceAlert;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;

class DisableEnableAlertInBulkCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:alert:update';
    const OPTION_NAME_PUBLISHER = 'publisher';
    const OPTION_NAME_DATA_SET = 'data-set';
    const OPTION_NAME_CONNECTED_DATA_SOURCE = 'connected-data-source';
    const OPTION_NAME_DATA_SOURCE = 'data-source';
    const OPTION_ALERT_TYPES = 'alert-types';
    const OPTION_ENABLE = 'enable';
    const OPTION_DISABLE = 'disable';

    const RETURN_VALUE_NO_ERROR = 0;
    const RETURN_VALUE_ERROR_PUBLISHER_NOT_FOUND = 1;
    const RETURN_VALUE_ERROR_DATA_SET_NOT_FOUND = 2;
    const RETURN_VALUE_ERROR_CONNECTED_DATA_SOURCE_NOT_FOUND = 3;
    const RETURN_VALUE_ERROR_DATA_SOURCE_NOT_FOUND = 4;
    const RETURN_VALUE_GENERAL_ERROR = 99;

    /** @var  Logger */
    private $logger;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Enable/disable alert setting on one/all connected data source(s) of dataSet or one dataSource by some alert types.' . "\n"
                . 'Please only use one of options -p, -t, -c, -r.')
            ->addOption(self::OPTION_NAME_PUBLISHER, 'p', InputOption::VALUE_OPTIONAL,
                'Disable/Enable for publisher')
            ->addOption(self::OPTION_NAME_DATA_SET, 't', InputOption::VALUE_OPTIONAL,
                'Disable/Enable for all connected data source of data set')
            ->addOption(self::OPTION_NAME_CONNECTED_DATA_SOURCE, 'c', InputOption::VALUE_OPTIONAL,
                'Disable/Enable for connected data source')
            ->addOption(self::OPTION_NAME_DATA_SOURCE, 'r', InputOption::VALUE_OPTIONAL,
                'Disable/Enable for data source')
            ->addOption(self::OPTION_ALERT_TYPES, 'a', InputOption::VALUE_REQUIRED,
                'Allow multiple alert types separated by comma.' . "\n"
                . 'Notice: alert types are difference from data source and data set.' . "\n"
                . 'For data set, supported alert types: ' . implode(', ', AbstractConnectedDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS) . '.' . "\n"
                . 'For data source, supported alert types: ' . implode(', ', AbstractDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS) . '.'
            )
            ->addOption(self::OPTION_ENABLE, 'E', InputOption::VALUE_NONE,
                'Enable alert settings. Default true if not provide both options "enable" and "disable".'
            )
            ->addOption(self::OPTION_DISABLE, 'D', InputOption::VALUE_NONE,
                'Disable alert settings.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $this->logger = $container->get('logger');
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');

        /* validate input */
        if (!$this->validateInput($input, $output)) {
            return self::RETURN_VALUE_GENERAL_ERROR;
        }

        $this->logger->info('Starting command...');

        /* get required param */
        $alertTypesString = $input->getOption(self::OPTION_ALERT_TYPES);
        $alertTypes = explode(',', $alertTypesString);

        $inputEnable = (bool)$input->getOption(self::OPTION_ENABLE);
        $inputDisable = (bool)$input->getOption(self::OPTION_DISABLE);
        $isEnable = $inputEnable || !$inputDisable;

        /* if handle by publisher */
        $publisherId = $input->getOption(self::OPTION_NAME_PUBLISHER);
        if (!empty($publisherId)) {
            $publisher = $this->publisherManager->find($publisherId);
            if (!$publisher instanceof PublisherInterface) {
                $output->writeln(sprintf('Could not find publisher with id %d', $publisherId));
                return self::RETURN_VALUE_ERROR_PUBLISHER_NOT_FOUND;
            }

            $this->updateAlertSettingsForPublisher($publisher, $alertTypes, $isEnable);
            return self::RETURN_VALUE_NO_ERROR;
        }

        /* if handle by data set */
        $dataSetId = $input->getOption(self::OPTION_NAME_DATA_SET);
        if (!empty($dataSetId)) {
            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                $output->writeln(sprintf('Could not find data set with id %d', $dataSetId));
                return self::RETURN_VALUE_ERROR_DATA_SET_NOT_FOUND;
            }

            $this->updateAlertSettingsForDataSet($dataSet, $alertTypes, $isEnable);
            return self::RETURN_VALUE_NO_ERROR;
        }

        /* if handle by connected data source */
        $connectedDataSourceId = $input->getOption(self::OPTION_NAME_CONNECTED_DATA_SOURCE);
        if ($connectedDataSourceId) {
            $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                $output->writeln(sprintf('Could not find connected data source with id %d', $connectedDataSourceId));
                return self::RETURN_VALUE_ERROR_CONNECTED_DATA_SOURCE_NOT_FOUND;
            }

            $this->updateAlertSettingsForConnectedDataSource($connectedDataSource, $alertTypes, $isEnable);
            return self::RETURN_VALUE_NO_ERROR;
        }

        /* if handle by data source */
        $dataSourceId = $input->getOption(self::OPTION_NAME_DATA_SOURCE);
        if ($dataSourceId) {
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface) {
                $output->writeln(sprintf('Could not find data source with id %d', $dataSourceId));
                return self::RETURN_VALUE_ERROR_DATA_SOURCE_NOT_FOUND;
            }

            $this->updateAlertSettingsForDataSource($dataSource, $alertTypes, $isEnable);
            return self::RETURN_VALUE_NO_ERROR;
        }

        $this->logger->info('Command run successfully');
        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        /* validate only one param p, d, r */
        $inputOptionsCount = 0;

        if ($input->getOption(self::OPTION_NAME_PUBLISHER)) {
            if (!$this->isPositiveInteger($input->getOption(self::OPTION_NAME_PUBLISHER))) {
                $output->writeln('Expected publisher id is integer');
                return false;
            }

            $inputOptionsCount++;
        }

        if ($input->getOption(self::OPTION_NAME_DATA_SET)) {
            if (!$this->isPositiveInteger($input->getOption(self::OPTION_NAME_DATA_SET))) {
                $output->writeln('Expected data set id is integer');
                return false;
            }

            $inputOptionsCount++;
        }

        if ($input->getOption(self::OPTION_NAME_CONNECTED_DATA_SOURCE)) {
            if (!$this->isPositiveInteger($input->getOption(self::OPTION_NAME_CONNECTED_DATA_SOURCE))) {
                $output->writeln('Expected connected data source id is integer');
                return false;
            }

            $inputOptionsCount++;
        }

        if ($input->getOption(self::OPTION_NAME_DATA_SOURCE)) {
            if (!$this->isPositiveInteger($input->getOption(self::OPTION_NAME_DATA_SOURCE))) {
                $output->writeln('Expected data source id is integer');
                return false;
            }

            $inputOptionsCount++;
        }

        if ($inputOptionsCount !== 1) {
            $output->writeln('Please only use one of options -p, -t, -c, -r');
            return false;
        }

        /* validate required param */
        $alertTypes = $input->getOption(self::OPTION_ALERT_TYPES);
        if (empty($alertTypes) || !is_string($alertTypes)) {
            $output->writeln(sprintf('Expected alert types is string (if multiple, separated by comma), got %s', (empty($alertTypes) ? 'empty' : $alertTypes)));
            return false;
        }

        return true;
    }

    /**
     * update Alert Settings For Publisher
     * @param PublisherInterface $publisher
     * @param array $alertTypes
     * @param bool $isEnable
     * @return bool
     */
    private function updateAlertSettingsForPublisher($publisher, array $alertTypes, $isEnable)
    {
        $this->logger->info(sprintf('Updating alert settings for Publisher #%d...', $publisher->getId()));

        /* update for all data sets */
        $dataSets = $this->dataSetManager->getDataSetForPublisher($publisher);
        $alertTypesForDataSet = array_values(
            array_filter($alertTypes, function ($alertType) {
                return in_array($alertType, AbstractConnectedDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS);
            })
        );

        foreach ($dataSets as $dataSet) {
            $this->updateAlertSettingsForDataSet($dataSet, $alertTypesForDataSet, $isEnable);
        }

        /* update for all connected data sources - already included in updating alert settings for each data set */

        /* update for all data sources */
        $dataSources = $this->dataSourceManager->getDataSourceForPublisher($publisher);
        $alertTypesForDataSource = array_values(
            array_filter($alertTypes, function ($alertType) {
                return in_array($alertType, AbstractDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS);
            })
        );

        foreach ($dataSources as $dataSource) {
            $this->updateAlertSettingsForDataSource($dataSource, $alertTypesForDataSource, $isEnable);
        }

        $this->logger->info(sprintf('Updating alert settings for Publisher #%d... done!', $publisher->getId()));
        return true;
    }

    /**
     * update Alert Settings For Data Set
     * @param DataSetInterface $dataSet
     * @param array $alertTypes
     * @param bool $isEnable
     * @return bool
     */
    private function updateAlertSettingsForDataSet(DataSetInterface $dataSet, array $alertTypes, $isEnable)
    {
        $this->logger->info(sprintf('Updating alert settings for Data Set #%d...', $dataSet->getId()));

        $connectedDataSources = $dataSet->getConnectedDataSources();
        foreach ($connectedDataSources as $connectedDataSource) {
            $this->updateAlertSettingsForConnectedDataSource($connectedDataSource, $alertTypes, $isEnable);
        }

        $this->logger->info(sprintf('Updating alert settings for Data Set #%d... done!', $dataSet->getId()));
        return true;
    }

    /**
     * update Alert Settings For Connected Data Source
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param array $alertTypes
     * @param bool $isEnable
     * @return bool
     */
    private function updateAlertSettingsForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, array $alertTypes, $isEnable)
    {
        $this->logger->info(sprintf('Updating alert settings for Connected Data Source #%d...', $connectedDataSource->getId()));

        $alertSettings = $connectedDataSource->getAlertSetting();
        if (!is_array($alertSettings)) {
            $alertSettings = [];
        }

        foreach ($alertTypes as $alertType) {
            switch ($alertType) {
                case ConnectedDataSourceAlertInterface::TYPE_DATA_ADDED:
                    if ($isEnable) {
                        if (!in_array($alertType, $alertSettings)) {
                            $alertSettings[] = ConnectedDataSourceAlertInterface::TYPE_DATA_ADDED;
                        }
                    } else {
                        foreach ($alertSettings as $key => &$alertSetting) {
                            if ($alertSetting == ConnectedDataSourceAlertInterface::TYPE_DATA_ADDED) {
                                unset($alertSettings[$key]);
                            }
                        }

                        // important: keep continuous array indexes after unset
                        $alertSettings = array_values($alertSettings);

                        // free memory
                        unset($alertSetting);
                    }

                    break;

                case ConnectedDataSourceAlertInterface::TYPE_IMPORT_FAILURE:
                    if ($isEnable) {
                        if (!in_array($alertType, $alertSettings)) {
                            $alertSettings[] = ConnectedDataSourceAlertInterface::TYPE_IMPORT_FAILURE;
                        }
                    } else {
                        foreach ($alertSettings as $key => &$alertSetting) {
                            if ($alertSetting == ConnectedDataSourceAlertInterface::TYPE_IMPORT_FAILURE) {
                                unset($alertSettings[$key]);
                            }
                        }

                        // important: keep continuous array indexes after unset
                        $alertSettings = array_values($alertSettings);

                        // free memory
                        unset($alertSetting);
                    }

                    break;

                default:
                    throw new InvalidArgumentException(sprintf('Not supported alert type %s', $alertType));
            }
        }

        // save
        $connectedDataSource->setAlertSetting($alertSettings);
        $this->connectedDataSourceManager->save($connectedDataSource);

        $this->logger->info(sprintf('Updating alert settings for Connected Data Source #%d... done!', $connectedDataSource->getId()));
        return true;
    }

    /**
     * update Alert Settings For Data Source
     * @param DataSourceInterface $dataSource
     * @param array $alertTypes
     * @param bool $isEnable
     * @return bool
     */
    private function updateAlertSettingsForDataSource(DataSourceInterface $dataSource, array $alertTypes, $isEnable)
    {
        $this->logger->info(sprintf('Updating alert settings for Data Source #%d...', $dataSource->getId()));

        $alertSettings = $dataSource->getAlertSetting();
        if (!is_array($alertSettings)) {
            $alertSettings = [];
        }

        foreach ($alertSettings as &$alertSetting) {
            if (!array_key_exists(DataSourceAlertInterface::ALERT_TYPE_KEY, $alertSetting)) {
                continue;
            }

            $oldAlertType = $alertSetting[DataSourceAlertInterface::ALERT_TYPE_KEY];

            foreach ($alertTypes as $alertType) {
                if (!in_array($alertType, AbstractDataSourceAlert::$SUPPORTED_ALERT_SETTING_KEYS)) {
                    throw new InvalidArgumentException(sprintf('Not supported alert type %s', $alertType));
                }

                if ($oldAlertType !== $alertType) {
                    continue;
                }

                switch ($alertType) {
                    case DataSourceAlertInterface::ALERT_TYPE_VALUE_WRONG_FORMAT:
                        $alertSetting[DataSourceAlertInterface::ALERT_ACTIVE_KEY] = $isEnable;
                        break;

                    case DataSourceAlertInterface::ALERT_TYPE_VALUE_DATA_RECEIVED:
                        $alertSetting[DataSourceAlertInterface::ALERT_ACTIVE_KEY] = $isEnable;
                        break;

                    case DataSourceAlertInterface::ALERT_TYPE_VALUE_DATA_NO_RECEIVED:
                        $alertSetting[DataSourceAlertInterface::ALERT_ACTIVE_KEY] = $isEnable;
                        break;
                }
            }
        }

        // free memory
        unset($alertSetting);

        // save
        $dataSource->setAlertSetting($alertSettings);
        $this->dataSourceManager->save($dataSource);

        $this->logger->info(sprintf('Updating alert settings for Data Source #%d... done!', $dataSource->getId()));
        return true;
    }

    /**
     * check if text is Positive Integer string
     *
     * @param string $text
     * @return bool
     */
    private function isPositiveInteger($text)
    {
        return preg_match('/^[0-9]+$/', $text) === 1;
    }
}