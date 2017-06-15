<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Model\Core\DataSetInterface;
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

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
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

        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());

        try {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            $this->logger->notice(sprintf('deleting data from %s with Connected data source (ID:%s)', $dataTable->getName(), $connectedDataSourceId));
            $this->conn->beginTransaction();
            $this->deleteDataByConnectedDataSourceId($dataTable->getName(), $this->conn, $connectedDataSourceId);
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

    private function deleteDataByConnectedDataSourceId($tableName, Connection $connection, $connectedDataSourceId)
    {
        /*
         * DELETE FROM __data_import_3 where __connected_data_source_id = :__connected_data_source_id
         */

        $deleteSql = sprintf(" DELETE FROM %s WHERE  %s = :%s",
            $tableName,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN
        );

        $qb = $connection->prepare($deleteSql);
        $qb->bindValue(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, $connectedDataSourceId);
        $qb->execute();
    }
}