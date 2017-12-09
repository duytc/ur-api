<?php

namespace UR\Worker\Job\Linear;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\ReloadParamsInterface;
use UR\Service\DataSet\Synchronizer;

class RemoveDataFromConnectedDataSourceSubJob implements SubJobInterface
{
    const JOB_NAME = 'removeConnectedDataSourceDataSubJob';

    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const DATA_SET_ID = 'data_set_id';

    private $em;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    protected $connectedDataSourceManager;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em,
                                ImportHistoryManagerInterface $importHistoryManager,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->importHistoryManager = $importHistoryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;

    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $startDate = $params->getParam(ReloadParamsInterface::RELOAD_START_DATE);
        $endDate = $params->getParam(ReloadParamsInterface::RELOAD_END_DATE);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->notice(sprintf('Connected data source with id = %d does not exist', $connectedDataSourceId));
            return;
        }

        /**
         * @var ConnectedDataSourceInterface $connectedDataSource ;
         */

        if ($startDate instanceof DateTime && $endDate instanceof DateTime) {
            $startDate = $startDate->format('Y-m-d');
            $endDate = $endDate->format('Y-m-d');
        }

        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());

        try {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            $this->logger->notice(sprintf('deleting data from %s with Connected data source (ID:%s)', $dataTable->getName(), $connectedDataSourceId));
            $this->conn->beginTransaction();
            $this->deleteDataByConnectedDataSourceId($dataTable->getName(), $this->conn, $connectedDataSourceId, $startDate, $endDate);
            //$this->importHistoryManager->deleteImportHistoryByConnectedDataSource($connectedDataSourceId);
            $this->conn->commit();
            $this->conn->close();
            $this->logger->notice('success delete data from data set');

        } catch (Exception $exception) {
            $this->logger->error(sprintf('cannot deleting data from connected data source (ID: %s) of data set (ID: %s) cause: %s', $connectedDataSourceId, $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }

    /**
     * @param $tableName
     * @param Connection $connection
     * @param $connectedDataSourceId
     * @param $startDate
     * @param $endDate
     */
    private function deleteDataByConnectedDataSourceId($tableName, Connection $connection, $connectedDataSourceId, $startDate, $endDate)
    {
        /*
         * DELETE FROM __data_import_3 where __connected_data_source_id = :__connected_data_source_id
         */
        $deleteSql = sprintf(" DELETE FROM %s WHERE  %s = :%s",
            $tableName,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN
        );

        if (!is_null($startDate) && !is_null($endDate)) {
            $dateTimeFields = $this->getDateTimeFields($connectedDataSourceId);
            foreach ($dateTimeFields as $dateTimeField) {
                $deleteCondition = sprintf("%s < %s AND %s > %s", $dateTimeField, $endDate, $dateTimeField, $startDate);
                $deleteSql = sprintf("%s AND %s", $deleteSql, $deleteCondition);
            }
        }

        $qb = $connection->prepare($deleteSql);
        $qb->bindValue(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, $connectedDataSourceId);
        $qb->execute();
    }

    /**
     * @param $connectedDataSourceId
     * @return array
     */
    private function getDateTimeFields($connectedDataSourceId)
    {
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return [];
        }

        $dataSetFields = $connectedDataSource->getDataSet()->getAllDimensionMetrics();
        $dateTimeFields = [];
        foreach ($dataSetFields as $field => $type) {
            if ($type === FieldType::DATE || $type === FieldType::DATETIME) {
                $dateTimeFields[] = $field;
            }
        }

        return $dateTimeFields;
    }

}