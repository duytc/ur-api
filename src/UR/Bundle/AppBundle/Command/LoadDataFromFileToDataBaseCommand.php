<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\Alert\ConnectedDataSource\DataAddedAlert;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportDataLogger;
use UR\Service\StringUtilTrait;

class LoadDataFromFileToDataBaseCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:data-set:load-file')
            ->addArgument('connectedDataSourceId', InputArgument::REQUIRED, 'Connected data source id')
            ->addArgument('dataSourceEntryId', InputArgument::REQUIRED, 'Data source entry id')
            ->addArgument('importId', InputArgument::OPTIONAL, 'Import history id. It will be created automatically if not provided')
            ->setDescription('Load data from entry file to data import table');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isImportFail = false;
        $errorCode = DataAddedAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY;
        $errorRow = null;
        $errorColumn = null;
        $errorContent = null;

        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        /**
         * @var Logger $logger
         */
        $logger = $container->get('logger');

        /** @var ImportDataLogger $importDataLogger */
        $importDataLogger = $container->get('ur.service.import.do_log');

        $dataSourceEntryManager = $container->get('ur.domain_manager.data_source_entry');
        $connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');
        $autoImport = $container->get('ur.worker.workers.auto_import_data');
        $importHistoryManager = $container->get('ur.domain_manager.import_history');
        $workerManager = $container->get('ur.worker.manager');
        $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();

        /* get inputs */
        $connectedDataSourceId = $input->getArgument('connectedDataSourceId');
        $dataSourceEntryId = $input->getArgument('dataSourceEntryId');
        $importId = $input->getArgument('importId');

        /* validate inputs */
        if (!$this->validateInput($connectedDataSourceId, $dataSourceEntryId, $importId, $output)) {
            throw new \Exception(sprintf('command run failed: params must integer'));
        }

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $dataSourceEntryManager->find($dataSourceEntryId);

        if ($dataSourceEntry === null) {
            throw new \Exception(sprintf('cannot find data source entry with id: %d ', $dataSourceEntryId));
        }

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $connectedDataSourceManager->find($connectedDataSourceId);

        if ($connectedDataSource->getDataSet() === null) {
            throw new \Exception(sprintf('no data set with connected data source id: %d ', $connectedDataSourceId));
        }

        if (!$importId) {
            /* create new import History */
            $importHistoryEntity = $importHistoryManager->createImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);
        } else {
            $importHistoryEntity = $importHistoryManager->find($importId);

            if (!$importHistoryEntity) {
                throw new \Exception(sprintf('can not find import history with id: %d', $importId));
            }
        }

        $publisherId = $connectedDataSource->getDataSet()->getPublisherId();

        try {
            /*
             * get import histories by data source entry and connected data source
             */
            $importHistories = $importHistoryManager->getImportHistoryByDataSourceEntry($dataSourceEntry, $connectedDataSource->getDataSet(), $importHistoryEntity);

            if ($importHistoryEntity === null) {
                throw new \Exception(sprintf('can not find import history with id: %d', $importId));
            }

            /*
             * call service load data to data base
             */
            $autoImport->loadingDataFromFileToDatabase($connectedDataSource, $dataSourceEntry, $importHistoryEntity);

            /* alert when successful*/
            $importSuccessAlert = $connectedDataSourceAlertFactory->getAlert(
                $importHistoryEntity->getId(),
                $connectedDataSource->getAlertSetting(),
                DataAddedAlert::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );

            if ($importSuccessAlert !== null) {
                $workerManager->processAlert($importSuccessAlert->getAlertCode(), $publisherId, $importSuccessAlert->getDetails());
            }

            $importHistoryManager->deletePreviousImports($importHistories);
            $logger->notice(
                sprintf('success importing file "%s" into data set "%s" (entry: %d, data set %d, connected data source: %d, data source: %d)',
                    $dataSourceEntry->getFileName(),
                    $connectedDataSource->getDataSet()->getName(),
                    $dataSourceEntry->getId(),
                    $connectedDataSource->getDataSet()->getId(),
                    $connectedDataSource->getId(),
                    $connectedDataSource->getDataSource()->getId()
                )
            );
        } catch (ImportDataException $e) { /* exception */
            $errorCode = $e->getAlertCode();
            $isImportFail = true;
            $importDataLogger->doImportLogging($errorCode, $publisherId, $connectedDataSource->getDataSource()->getId(), $dataSourceEntry->getId(), $e->getRow(), $e->getColumn());
            $errorRow = $e->getRow();
            $errorColumn = $e->getColumn();
            $errorContent = $e->getContent();
        } catch (\Exception $exception) {
            $errorCode = ImportFailureAlert::ALERT_CODE_UN_EXPECTED_ERROR;
            $isImportFail = true;
            $message = sprintf('failed to import file "%" into data set "%s" (entry: %d, data set %d, connected data source: %d, data source: %d, message: %s)',
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSet()->getName(),
                $dataSourceEntry->getId(),
                $connectedDataSource->getDataSet()->getId(),
                $connectedDataSource->getId(),
                $connectedDataSource->getDataSource()->getId(),
                $exception->getMessage()
            );
            $logger->error($message);
        }

        if ($isImportFail) {

            $failureAlert = $connectedDataSourceAlertFactory->getAlert(
                $importHistoryEntity->getId(),
                $connectedDataSource->getAlertSetting(),
                $errorCode, $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                $errorColumn,
                $errorRow,
                $errorContent
            );

            /*delete import history when fail*/
            $importHistoryManager->delete($importHistoryEntity);

            if ($failureAlert != null) {
                $workerManager->processAlert($errorCode, $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails());
            }
        }

        return 0;
    }

    /**
     * @param $connectedDataSourceId
     * @param $dataSourceEntryId
     * @param $importId
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($connectedDataSourceId, $dataSourceEntryId, $importId, OutputInterface $output)
    {
        function isInteger($input)
        {
            return (ctype_digit(strval($input)));
        }

        // validate input
        if (!isInteger($connectedDataSourceId) || !isInteger($dataSourceEntryId)) {
            $output->writeln(sprintf('command run failed: params must be an integer'));
            return false;
        }

        if ($importId && !isInteger($dataSourceEntryId)) {
            $output->writeln(sprintf('command run failed: importId must be an integer'));
            return false;
        }

        return true;
    }
}