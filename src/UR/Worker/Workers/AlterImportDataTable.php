<?php

namespace UR\Worker\Workers;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use UR\DomainManager\DataSetManagerInterface;
use UR\Exception\SqlLockTableException;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\LockingDatabaseTable;
use UR\Service\DataSet\Synchronizer;
use UR\Service\Import\DataSetImportJobQueueService;

class AlterImportDataTable
{
    /**
     * @var DataSetManagerInterface $dataSetManager
     */
    private $dataSetManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var DataSetImportJobQueueService
     */
    private $dataSetImportJobQueueService;

    private $conn;

    private $lockingDatabaseTable;

    /**
     * @var PheanstalkProxyInterface
     */
    private $queue;

    private $logger;

    /** @var int in seconds */
    private $delayForJobWhenPutBack = 5;

    /**
     * AlterImportDataTable constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param EntityManagerInterface $entityManager
     * @param $queue
     * @param Logger $logger
     * @param DataSetImportJobQueueService $dataSetImportJobQueueService
     * @param $delayForJobWhenPutBack
     */
    public function __construct(DataSetManagerInterface $dataSetManager, EntityManagerInterface $entityManager, $queue, Logger $logger, DataSetImportJobQueueService $dataSetImportJobQueueService, $delayForJobWhenPutBack)
    {
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
        $this->lockingDatabaseTable = new LockingDatabaseTable($this->conn);
        $this->queue = $queue;
        $this->logger = $logger;
        $this->dataSetImportJobQueueService = $dataSetImportJobQueueService;
        $this->delayForJobWhenPutBack = (is_integer($delayForJobWhenPutBack) && $delayForJobWhenPutBack > 0) ? $delayForJobWhenPutBack : 5;
    }

    public function alterDataSetTable(stdClass $params, Pheanstalk_Job $job, $tube)
    {
        $dataSetId = $params->dataSetId;
        $importJobId = $params->importJobId;

        $exeCuteJob = $this->dataSetImportJobQueueService->isExecuteJob($dataSetId, $importJobId, $this->logger);
        if (!$exeCuteJob instanceof DataSetImportJobInterface) {
            $this->queue->putInTube($tube, $job->getData(), 1024, $this->delayForJobWhenPutBack);
            return;
        }

        /**
         * @var DataSetInterface $dataSet
         */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
        }

        $deletedColumns = $params->deletedColumns === null ? [] : $params->deletedColumns;
        $updateColumns = $params->updateColumns === null ? [] : $params->updateColumns;
        $newColumns = $params->newColumns === null ? [] : $params->newColumns;

        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            $this->dataSetImportJobQueueService->deleteJob($exeCuteJob);
            return;
        }

        try {
            $this->lockingDatabaseTable->lockTable($dataTable->getName());
        } catch (SqlLockTableException $exception) {
            $this->queue->putInTube($tube, $job->getData(), 1024, $this->delayForJobWhenPutBack);
            return;
        }

        $delCols = [];
        $addCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            try {
                $delCol = $dataTable->getColumn($deletedColumn);
                $delCols[] = $delCol;
                $dataTable->dropColumn($deletedColumn);
                if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $dataTable->dropColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $deletedColumn));
                    $dataTable->dropColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $deletedColumn));
                    $dataTable->dropColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $deletedColumn));
                }
            } catch (SchemaException $exception) {
                $this->logger->warning($exception->getMessage());
            }
        }

        foreach ($newColumns as $newColumn => $type) {
            if (strcmp($type, FieldType::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::INTEGER, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DATE) === 0 OR strcmp($type, FieldType::DATETIME) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::DATE, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $updateTable = new TableDiff($dataTable->getName(), $addCols, [], $delCols, [], [], []);
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $alterSqls = $this->conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
            foreach ($updateColumns as $oldName => $newName) {
                $curCol = $dataTable->getColumn($oldName);
                $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldName, $newName, $curCol->getType()->getName());
                if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                    $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                }
                $alterSqls[] = $sql;

                if ($curCol->getType()->getName() == FieldType::DATE || $curCol->getType()->getName() == FieldType::DATETIME) {
                    $oldDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $oldName);
                    $oldMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $oldName);
                    $oldYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $oldName);

                    $newDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newName);
                    $newMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newName);
                    $newYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newName);

                    if ($dataTable->hasColumn($oldDay)) {
                        $sqlDay = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldDay, $newDay, Type::INTEGER);
                        $alterSqls[] = $sqlDay;
                    }

                    if ($dataTable->hasColumn($oldMonth)) {
                        $sqlMonth = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldMonth, $newMonth, Type::INTEGER);
                        $alterSqls[] = $sqlMonth;
                    }

                    if ($dataTable->hasColumn($oldYear)) {
                        $sqlYear = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldYear, $newYear, Type::INTEGER);
                        $alterSqls[] = $sqlYear;
                    }
                }
            }

            foreach ($alterSqls as $alterSql) {
                $this->conn->exec($alterSql);
            }

        } catch (\Exception $e) {
            $this->lockingDatabaseTable->unLockTable();
            $this->logger->error($e->getMessage());
            throw new \mysqli_sql_exception("Cannot Sync Schema " . $schema->getName());
        }

        $this->dataSetImportJobQueueService->deleteJob($exeCuteJob);

        $this->lockingDatabaseTable->unLockTable();
    }
}