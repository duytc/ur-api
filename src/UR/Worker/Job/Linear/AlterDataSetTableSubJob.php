<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use Doctrine\DBAL\Types\Type;
use UR\Service\DataSet\FieldType;

class AlterDataSetTableSubJob implements SubJobInterface
{
    const JOB_NAME = 'alterDataSetTableSubJob';

    const DATA_SET_ID = 'data_set_id';

    const NEW_FIELDS = 'new_fields';
    const UPDATE_FIELDS = 'update_fields';
    const DELETED_FIELDS = 'deleted_fields';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(LoggerInterface $logger, DataSetManagerInterface $dataSetManager, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->em = $em;
        $this->conn = $em->getConnection();
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);
        $newColumns = $params->getRequiredParam(self::NEW_FIELDS);
        $updateColumns = $params->getRequiredParam(self::UPDATE_FIELDS);
        $deletedColumns = $params->getRequiredParam(self::DELETED_FIELDS);
        try {
            /**
             * @var DataSetInterface $dataSet
             */
            $dataSet = $this->dataSetManager->find($dataSetId);

            if ($dataSet === null) {
                throw new Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

            // check if table not existed
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }


            // do rename columns before add or delete column
            $renameColumnsSqls = [];
            $columns = $dataSet->getAllDimensionMetrics();
            foreach ($updateColumns as $oldName => $newName) {
                if (!$dataTable->hasColumn($oldName)) {
                    // make sure old column existing. It may be not exist if data set is not sync
                    continue;
                }

                $curCol = $dataTable->getColumn($oldName);
                $type = $curCol->getType()->getName();

                // map DBAL type to our custom type
                // e.g type string => type largeText if new column type is largeText
                // e.g type string => type text if new column type is text
                if ($type === Type::STRING && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::LARGE_TEXT) {
                    $type = FieldType::LARGE_TEXT;
                } else if ($type === Type::STRING && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::TEXT) {
                    $type = FieldType::TEXT;
                }

                if ($dataTable->hasColumn($oldName)) {
                    // build type-with-length as native sql type depend on our custom type
                    // e.g type=largeText => native sql is ...varchar(65535)
                    // e.g type=text => native sql is ...varchar(512)
                    $typeWithLength = ($type === FieldType::LARGE_TEXT)
                        ? sprintf('%s(%s)', FieldType::$MAPPED_FIELD_TYPE_DATABASE_TYPE[FieldType::LARGE_TEXT], Synchronizer::FIELD_LENGTH_LARGE_TEXT)
                        : (($type === FieldType::TEXT)
                            ? sprintf('%s(%s)', FieldType::$MAPPED_FIELD_TYPE_DATABASE_TYPE[FieldType::LARGE_TEXT], Synchronizer::FIELD_LENGTH_TEXT)
                            : $type
                        );

                    $sql = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldName, $newName, $typeWithLength);
                    if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                        $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                    }
                    $renameColumnsSqls[] = $sql;
                }

                if ($curCol->getType()->getName() == FieldType::DATE || $curCol->getType()->getName() == FieldType::DATETIME) {
                    $oldDay = Synchronizer::getHiddenColumnDay($oldName);
                    $oldMonth = Synchronizer::getHiddenColumnMonth($oldName);
                    $oldYear = Synchronizer::getHiddenColumnYear($oldName);

                    $newDay = Synchronizer::getHiddenColumnDay($newName);
                    $newMonth = Synchronizer::getHiddenColumnMonth($newName);
                    $newYear = Synchronizer::getHiddenColumnYear($newName);

                    if ($dataTable->hasColumn($oldDay)) {
                        $sqlDay = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldDay, $newDay, Type::INTEGER);
                        $renameColumnsSqls[] = $sqlDay;
                    }

                    if ($dataTable->hasColumn($oldMonth)) {
                        $sqlMonth = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldMonth, $newMonth, Type::INTEGER);
                        $renameColumnsSqls[] = $sqlMonth;
                    }

                    if ($dataTable->hasColumn($oldYear)) {
                        $sqlYear = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldYear, $newYear, Type::INTEGER);
                        $renameColumnsSqls[] = $sqlYear;
                    }
                }
            }

            // execute rename sql
            foreach ($renameColumnsSqls as $alterSql) {
                $this->conn->exec($alterSql);
            }

            //get table again after rename columns
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

            $delCols = [];
            $addCols = [];
            foreach ($deletedColumns as $deletedColumn => $type) {
                $delCols[] = $dataTable->getColumn($deletedColumn);
                if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnDay($deletedColumn));
                    $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnMonth($deletedColumn));
                    $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnYear($deletedColumn));
                }
            }

            $deletedColumnsTable = new TableDiff($dataTable->getName(), [], [], $delCols);
            $deleteColumnsSqls = $this->conn->getDatabasePlatform()->getAlterTableSQL($deletedColumnsTable);
            foreach ($deleteColumnsSqls as $deleteColumnsSql) {
                $this->conn->exec($deleteColumnsSql);
            }

            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            foreach ($newColumns as $newColumn => $type) {
                if ($type === FieldType::NUMBER) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                    $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null]);
                } else if ($type === FieldType::DECIMAL) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                    $addCols[] = $dataTable->addColumn($newColumn, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
                } else if ($type === FieldType::LARGE_TEXT) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                    $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_LARGE_TEXT]);
                } else if ($type === FieldType::TEXT) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                    $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_TEXT]);
                } else if ($type === FieldType::DATE OR $type === FieldType::DATETIME) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                    $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null]);

                    $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];
                    $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnDay($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                    $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnMonth($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                    $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnYear($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                } else {
                    $addCols[] = $dataTable->addColumn($newColumn, $type, ['notnull' => false, 'default' => null]);
                }
            }

            $addedColumnsTable = new TableDiff($dataTable->getName(), $addCols);
            $addColumnsSqls = $this->conn->getDatabasePlatform()->getAlterTableSQL($addedColumnsTable);

            // execute add or delete columns sql
            foreach ($addColumnsSqls as $addColumnsSql) {
                $this->conn->exec($addColumnsSql);
            }

            // update indexes
            $dataSetSynchronizer->updateIndexes($this->conn, $dataTable, $dataSet);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('cannot edit data set (ID: %s) cause: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
            $this->conn->close();
        }
    }
}