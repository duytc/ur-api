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
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;

class DisableEnableAlertInBulkCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:alert:update';
    const PUBLISHER_ID = 'publisher-id';
    const DATA_SET_ID = 'data-set-id';
    const DATA_SOURCE_ID = 'data-source-id';
    const ENABLE_NEW_FILES = 'enable-new-files';
    const ENABLE_ERRORS = 'enable-error';

    /** @var  Logger */
    private $logger;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Enable/disable alert setting on dataSource, connected dataSource')
            ->addOption(self::PUBLISHER_ID, 'p', InputOption::VALUE_REQUIRED,
                'Enable for publisher')
            ->addOption(self::DATA_SET_ID, 'd', InputOption::VALUE_REQUIRED,
                'Enable for data set')
            ->addOption(self::DATA_SOURCE_ID, 'i', InputOption::VALUE_REQUIRED,
                'Enable for data source')
            ->addOption(self::ENABLE_NEW_FILES, 'f', InputOption::VALUE_REQUIRED,
                'Alerts relate when new files import, choose Yes to show, No to hide alerts')
            ->addOption(self::ENABLE_ERRORS, 'r', InputOption::VALUE_REQUIRED,
                'Error alerts when import file and parse file, choose Yes to show, No to hide alerts');
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

        if (!$this->validateInput($input, $output)) {
            $output->writeln('Quit command');
            return;
        }

        $this->logger->info('starting command...');

        $dataSources = $this->getDataSourcesFromInput($input);
        $connectedDataSources = $this->getConnectedDataSourcesFromInput($input);

        $enableNewFiles = $this->getOptionBoolean($input, self::ENABLE_NEW_FILES);
        $enableErrors = $this->getOptionBoolean($input, self::ENABLE_ERRORS);

        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $alertSetting = $this->updateAlertSettingsForDataSource($dataSource->getAlertSetting(), $enableNewFiles, $enableErrors);
            $dataSource->setAlertSetting($alertSetting);
            $this->dataSourceManager->save($dataSource);
            $this->logger->info('Update alert setting for data source ' . $dataSource->getName() . ', id ' . $dataSource->getId());
        }

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $alertSetting = $this->updateAlertSettingsForConnectedDataSource($connectedDataSource->getAlertSetting(), $enableNewFiles, $enableErrors);
            $connectedDataSource->setAlertSetting($alertSetting);
            $this->connectedDataSourceManager->save($connectedDataSource);
            $this->logger->info('Update alert setting for connected data source ' . $connectedDataSource->getName() . ', id ' . $connectedDataSource->getId());
        }

        $this->logger->info('command run successfully...');
    }


    /**
     * @param InputInterface $input
     * @return \UR\Model\Core\DataSourceInterface[]
     * @internal param OutputInterface $output
     */
    private function getDataSourcesFromInput($input)
    {
        if ($input->getOption(self::PUBLISHER_ID)) {
            $publisher = $this->publisherManager->find($input->getOption(self::PUBLISHER_ID));
            if (!$publisher instanceof PublisherInterface) {
                return [];
            }
            return $this->dataSourceManager->getDataSourceForPublisher($publisher);
        }

        if ($input->getOption(self::DATA_SET_ID)) {
            $dataSet = $this->dataSetManager->find($input->getOption(self::DATA_SET_ID));
            if (!$dataSet instanceof DataSetInterface) {
                return [];
            }
            return $this->dataSourceManager->getDataSourceByDataSet($dataSet);
        }

        if ($input->getOption(self::DATA_SOURCE_ID)) {
            $dataSource = $this->dataSourceManager->find($input->getOption(self::DATA_SOURCE_ID));
            if (!$dataSource instanceof DataSourceInterface) {
                return [];
            }
            return [$dataSource];
        }
        return [];
    }

    /**
     * @param $input
     * @return \UR\Model\Core\ConnectedDataSourceInterface[]
     * @internal param $output
     */
    private function getConnectedDataSourcesFromInput($input)
    {
        $dataSources = $this->getDataSourcesFromInput($input);

        $connected = [];
        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }
            $connected = array_merge($connected, $this->connectedDataSourceManager->getConnectedDataSourceByDataSource($dataSource));
        }
        return array_values($connected);
    }

    /**
     * @param $alertSettings
     * @param $enableNewFiles
     * @param $enableErrors
     * @return mixed
     */
    private function updateAlertSettingsForDataSource($alertSettings, $enableNewFiles, $enableErrors)
    {
        if (!is_array($alertSettings)) {
            return $alertSettings;
        }

        foreach ($alertSettings as &$alertSetting) {
            if (array_key_exists(DataSourceAlertInterface::ALERT_TYPE_KEY, $alertSetting)) {
                if ($alertSetting[DataSourceAlertInterface::ALERT_TYPE_KEY] == DataSourceAlertInterface::ALERT_WRONG_FORMAT_KEY) {
                    $alertSetting[DataSourceAlertInterface::ALERT_ACTIVE_KEY] = $enableErrors;
                }
                if ($alertSetting[DataSourceAlertInterface::ALERT_TYPE_KEY] == DataSourceAlertInterface::ALERT_DATA_RECEIVED_KEY) {
                    $alertSetting[DataSourceAlertInterface::ALERT_ACTIVE_KEY] = $enableNewFiles;
                }
            }
        }
        return array_values(array_unique($alertSettings, SORT_REGULAR));
    }

    /**
     * @param $alertSettings
     * @param $enableNewFiles
     * @param $enableErrors
     * @return mixed
     */
    private function updateAlertSettingsForConnectedDataSource($alertSettings, $enableNewFiles, $enableErrors)
    {
        if (!is_array($alertSettings)) {
            return $alertSettings;
        }

        if ($enableNewFiles) {
            $alertSettings[] = ConnectedDataSourceAlertInterface::DATA_ADDED;
            $alertSettings = array_values($alertSettings);
        } else {
            foreach ($alertSettings as $key => &$alertSetting) {
                if ($alertSetting == ConnectedDataSourceAlertInterface::DATA_ADDED) {
                    unset($alertSettings[$key]);
                }
            }
        }

        if ($enableErrors) {
            $alertSettings[] = ConnectedDataSourceAlertInterface::IMPORT_FAILURE;
            $alertSettings = array_values($alertSettings);
        } else {
            foreach ($alertSettings as $key => &$alertSetting) {
                if ($alertSetting == ConnectedDataSourceAlertInterface::IMPORT_FAILURE) {
                    unset($alertSettings[$key]);
                }
            }
        }

        return array_values(array_unique($alertSettings, SORT_REGULAR));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($input, $output)
    {
        $count = 0;
        if ($input->getOption(self::PUBLISHER_ID)) {
            $count++;
        }
        if ($input->getOption(self::DATA_SET_ID)) {
            $count++;
        }
        if ($input->getOption(self::DATA_SOURCE_ID)) {
            $count++;
        }
        if ($count < 1 || $count > 1) {
            $message = 'Do not use option -p, -d, -i together, use only one of them';
            $output->writeln($message);
            return false;
        }

        $publisherId = $input->getOption(self::PUBLISHER_ID);
        if ($publisherId) {
            $publisher = $this->publisherManager->find($publisherId);
            if (!$publisher instanceof PublisherInterface || !$this->isNumeric($publisherId)) {
                $message = 'Can not find publisher with id ' . $publisherId;
                $output->writeln($message);
                return false;
            }
        }

        $dataSetId = $input->getOption(self::DATA_SET_ID);
        if ($dataSetId) {
            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface || !$this->isNumeric($dataSetId)) {
                $message = 'Can not find data set with id ' . $dataSetId;
                $output->writeln($message);
                return false;
            }
        }

        $dataSourceId = $input->getOption(self::DATA_SOURCE_ID);
        if ($dataSourceId) {
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface || !$this->isNumeric($dataSourceId)) {
                $message = 'Can not find data source with id ' . $dataSourceId;
                $output->writeln($message);
                return false;
            }
        }

        if ($input->getOption(self::ENABLE_NEW_FILES)) {
            if (!$this->isValidateYesNo($input->getOption(self::ENABLE_NEW_FILES))) {
                $message = 'Use yes or no for option ' . self::ENABLE_NEW_FILES;
                $output->writeln($message);
                return false;
            }
        }

        if ($input->getOption(self::ENABLE_ERRORS)) {
            if (!$this->isValidateYesNo($input->getOption(self::ENABLE_ERRORS))) {
                $message = 'Use yes or no for option ' . self::ENABLE_ERRORS;
                $output->writeln($message);
                return false;
            }
        }

        return true;
    }

    /**
     * @param InputInterface $input
     * @param $option
     * @return bool
     */
    private function getOptionBoolean($input, $option)
    {
        if ($input->getOption($option)) {
            $rawText = $input->getOption($option);
            return $this->isYesOrNo($rawText);
        }
        return true;
    }

    /**
     * @param $text
     * @return bool
     */
    private function isYesOrNo($text)
    {
        $text = strtolower(trim($text));
        if ($text == 'y' || $text == 'yes' || $text == 'show' || $text == 'true' || $text == 't') {
            return true;
        }

        if ($text == 'n' || $text == 'no' || $text == 'hide' || $text == 'false' || $text == 'f') {
            return false;
        }

        if ($text != '0') {
            return true;
        }

        return false;
    }

    /**
     * @param $text
     * @return bool
     */
    private function isValidateYesNo($text)
    {
        $text = strtolower(trim($text));
        if ($text == 'y' || $text == 'yes' || $text == 'n' || $text == 'no') {
            return true;
        }
        return false;
    }

    /**
     * @param $text
     * @return int
     */
    private function isNumeric($text)
    {
        return preg_match('/^[0-9]+$/', $text);
    }
}