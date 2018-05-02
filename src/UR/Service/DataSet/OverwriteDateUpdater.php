<?php


namespace UR\Service\DataSet;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Redis;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;

class OverwriteDateUpdater implements OverwriteDateUpdaterInterface
{
    const LOCK_TEMPLATE = "data-set-%s-update-overwrite_date";
    const REDIS_KEY_TIME_OUT = 6;

    /** @var DataSetManagerInterface */
    private $dataSetManager;

    /** @var Logger */
    private $logger;

    /** @var EntityManagerInterface */
    private $em;

    /**
     * @var Connection
     */
    private $conn;

    /** @var Redis */
    private $redis;


    /**
     * OverwriteDateUpdater constructor.
     * @param Logger|LoggerInterface $logger
     * @param EntityManagerInterface $em
     * @param DataSetManagerInterface $dataSetManager
     */
    public function __construct(LoggerInterface $logger, EntityManagerInterface $em, DataSetManagerInterface $dataSetManager, Redis $redis)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->dataSetManager = $dataSetManager;
        $this->redis = $redis;
    }

    /**
     * @param $dataSetId
     */
    public function updateOverwriteDate($dataSetId)
    {
        $lockKey = sprintf(self::LOCK_TEMPLATE, $dataSetId);
        if ($this->redis->exists($lockKey)) {
            //Other process is updating overwrite date for this data set. Quit job
            return;
        }

        //Lock for 6 seconds
        $this->redis->set($lockKey, 1, self::REDIS_KEY_TIME_OUT);

        try {
            $dataSet = $this->dataSetManager->find($dataSetId);

            if (!$dataSet instanceof DataSetInterface) {
                throw new Exception(sprintf('Could not find data data set with ID: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());

            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            if ($dataSet->getAllowOverwriteExistingData()) {
                $this->logger->notice('begin updating __overwrite_date for data set');
                $this->conn->beginTransaction();
                $this->setOverwriteDateToDataImportTable($dataTable->getName());
                $this->conn->commit();
                $this->logger->notice('success updating __overwrite_date for data set');
            }

            $this->dataSetManager->save($dataSet);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('cannot update __overwrite_date to Data Set (ID: %s) cause: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }

        //Release lock
        $this->redis->del($lockKey);
    }

    /**
     * @param $tableName
     */
    private function setOverwriteDateToDataImportTable($tableName)
    {
        /*
         * CREATE TEMPORARY TABLE concurrency_example_overwrite (INDEX idx_1 (__unique_id, __entry_date, __import_id))
         * SELECT
            __unique_id,
            __entry_date,
            max(__import_id)  __import_id
            FROM (
                SELECT
                __unique_id,
                max(__entry_date) __entry_date,
                 __import_id
                FROM
                    (
                        SELECT
                        __unique_id,
                        __entry_date,
                        __import_id,
                        __connected_data_source_id
                    FROM __data_import_21
                    ORDER BY __entry_date DESC, __import_id DESC
                        LIMIT 18446744073709551615
                       ) rs1 GROUP BY __unique_id ORDER BY __import_id DESC
                LIMIT 18446744073709551615
               )rs2 group by __unique_id
         * UPDATE __data_import_3 set __overwrite_date = :__overwrite_date where __overwrite_date IS null AND (__unique_id, __entry_date, __import_id) NOT IN(SELECT * FROM concurrency_example_overwrite)
         */

        $concurrencyExampleOverwriteTable = sprintf("%s_%s", "concurrency_example_overwrite", bin2hex(random_bytes(5)));

        $index = sprintf("INDEX idx_1 (%s, %s, %s)", DataSetInterface::UNIQUE_ID_COLUMN, DataSetInterface::ENTRY_DATE_COLUMN, DataSetInterface::IMPORT_ID_COLUMN);

        $rs1Select = sprintf('SELECT %s, %s, %s, %s, %s',
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN
        );

        $rs1From = sprintf('FROM %s ORDER BY %s DESC, %s DESC LIMIT 18446744073709551615',
            $tableName,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs1Query = sprintf('(%s %s) as rs1', $rs1Select, $rs1From);

        $rs2Select = sprintf('SELECT %s, %s, MAX(%s) %s, %s',
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs2From = sprintf('FROM %s GROUP BY %s ORDER BY %s DESC LIMIT 18446744073709551615',
            $rs1Query,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs2Query = sprintf('(%s %s) as rs2', $rs2Select, $rs2From);

        $select = sprintf('SELECT %s, %s, %s, max(%s) %s FROM %s GROUP BY %s',
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            $rs2Query,
            DataSetInterface::UNIQUE_ID_COLUMN
        );

        $createTempTableSql = sprintf("CREATE TEMPORARY TABLE %s (%s) %s;", $concurrencyExampleOverwriteTable, $index, $select);

        $updateToNullWhere = sprintf("%s IS NOT null AND (%s, %s, %s, %s) IN (SELECT %s, %s, %s, %s FROM %s)",
            DataSetInterface::OVERWRITE_DATE,
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            $concurrencyExampleOverwriteTable
        );

        $updateToCurrentWhere = sprintf("%s IS null AND (%s, %s, %s, %s) NOT IN (SELECT %s, %s, %s, %s FROM %s)",
            DataSetInterface::OVERWRITE_DATE,
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            $concurrencyExampleOverwriteTable
        );

        $setOverwriteDateToNull = sprintf(" UPDATE %s set %s = NULL where %s;",
            $tableName,
            DataSetInterface::OVERWRITE_DATE,
            $updateToNullWhere
        );

        $setOverwriteDateToCurrent = sprintf(" UPDATE %s set %s = now() where %s;",
            $tableName,
            DataSetInterface::OVERWRITE_DATE,
            $updateToCurrentWhere
        );

        $updateOverwriteSql = sprintf("%s %s %s", $createTempTableSql, $setOverwriteDateToCurrent, $setOverwriteDateToNull);
        $qb = $this->conn->prepare($updateOverwriteSql);
        $qb->execute();
    }

}