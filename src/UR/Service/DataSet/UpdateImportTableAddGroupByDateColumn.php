<?php

namespace UR\Service\DataSet;

use DateTime;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use Doctrine\DBAL\Types\Type;

class UpdateImportTableAddGroupByDateColumn
{
    /**@var DataSetManagerInterface */
    protected $dataSetManager;

    /**@var EntityManager */
    protected $em;

    /**@var Logger */
    protected $logger;

    /**
     * UpdateImportTableAddGroupByDateColumn constructor.
     * @param EntityManager $em
     * @param Logger $logger
     */
    public function __construct(EntityManager $em, Logger $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return bool
     */
    public function updateDataSetForDateFields(DataSetInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->error("Data Set not exist ");
            return false;
        }

        $this->logger->info(sprintf('start updating date and datetime fields in data set: ' . $dataSet->getId()));

        $conn = $this->em->getConnection();
        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        if (!$dataSetTable) {
            $this->logger->error(sprintf("DataSet table %s not exist ", $dataSet->getId()));
            return false;
        }

        /** @var DataSetInterface[] $dataSetHaveDateFields */
        $dataSetHaveDateFields = [];

        $dimensions = $dataSet->getDimensions();
        $metrics = $dataSet->getMetrics();
        $fields = array_merge($dimensions, $metrics);

        foreach ($fields as $field => $type) {
            if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                if (
                    !in_array($dataSet, $dataSetHaveDateFields) && (
                        !$dataSetTable->hasColumn(Synchronizer::getHiddenColumnDay($field)) ||
                        !$dataSetTable->hasColumn(Synchronizer::getHiddenColumnMonth($field)) ||
                        !$dataSetTable->hasColumn(Synchronizer::getHiddenColumnYear($field)))
                ) {
                    $dataSetHaveDateFields[] = $dataSet;
                }
            }
        }

        foreach ($dataSetHaveDateFields as $dataSetHaveDateField) {
            $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSetHaveDateField->getId());

            $addCols = [];

            foreach ($fields as $newColumn => $type) {
                if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];

                    if (!$dataSetTable->hasColumn(Synchronizer::getHiddenColumnDay($newColumn))) {
                        $addCols[] = $dataSetTable->addColumn(Synchronizer::getHiddenColumnDay($newColumn), $colTypeDayOrMonthOrYear, ["notnull" => false, "default" => null]);
                    }

                    if (!$dataSetTable->hasColumn(Synchronizer::getHiddenColumnMonth($newColumn))) {
                        $addCols[] = $dataSetTable->addColumn(Synchronizer::getHiddenColumnMonth($newColumn), $colTypeDayOrMonthOrYear, ["notnull" => false, "default" => null]);
                    }

                    if (!$dataSetTable->hasColumn(Synchronizer::getHiddenColumnYear($newColumn))) {
                        $addCols[] = $dataSetTable->addColumn(Synchronizer::getHiddenColumnYear($newColumn), $colTypeDayOrMonthOrYear, ["notnull" => false, "default" => null]);
                    }
                }
            }

            $updateTable = new TableDiff($dataSetTable->getName(), $addCols, [], [], [], [], []);

            try {
                $dataSetSynchronizer->syncSchema($schema);
                $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
                foreach ($alterSqls as $alterSql) {
                    $conn->exec($alterSql);
                }
            } catch (\Exception $e) {
                $this->logger->error("Cannot Sync Schema " . $schema->getName());
            }

            $qb = $conn->createQueryBuilder();
            $qb->addSelect(DataSetInterface::ID_COLUMN);

            foreach ($fields as $field => $type) {
                if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $qb->addSelect($field);
                }
            }

            $qb->from($dataSetTable->getName());
            $rows = $qb->execute()->fetchAll();

            foreach ($rows as $row) {
                $id = $row[DataSetInterface::ID_COLUMN];
                unset($row[DataSetInterface::ID_COLUMN]);

                foreach ($row as $k => $item) {
                    if ($item === null) {
                        unset($row[$k]);
                    }
                }

                $updateQb = $conn->createQueryBuilder();
                $numberValidDates = 0;

                foreach ($row as $index => $date) {
                    if (!$date) {
                        continue;
                    }

                    if (DateTime::createFromFormat('Y-m-d', $date)) {
                        $date = DateTime::createFromFormat('Y-m-d', $date);
                    } else {
                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $date);
                    }

                    if ($date instanceof DateTime) {
                        $numberValidDates++;
                        $month = $date->format('n');
                        $year = $date->format('Y');
                        $day = $date->format('j');

                        if ($day) {
                            $updateQb->update($dataSetTable->getName(), 't')->set(Synchronizer::getHiddenColumnDay($index), $day);
                        }

                        if ($month) {
                            $updateQb->update($dataSetTable->getName(), 't')->set(Synchronizer::getHiddenColumnMonth($index), $month);
                        }

                        if ($year) {
                            $updateQb->update($dataSetTable->getName(), 't')->set(Synchronizer::getHiddenColumnYear($index), $year);
                        }
                    }
                }

                if ($numberValidDates > 0) {
                    $updateQb->where('t.__id=:table_id')->setParameter(':table_id', $id);

                    try {
                        $updateQb->execute();
                    } catch (\Exception $e) {
                        $this->logger->alert($e->getMessage());
                    }
                }
            }
        }

        $this->logger->info(sprintf('command updating date and datetime fields successfully on dataSet: ' . $dataSet->getId()));
        return true;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return bool
     */
    public function updateDataSetForOverrideDateField(DataSetInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->error("Data Set not exist ");
            return false;
        }

        $this->logger->info(sprintf('start updating override date field on data set: ' . $dataSet->getId()));

        $conn = $this->em->getConnection();
        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        if (!$dataSetTable) {
            $this->logger->error(sprintf("DataSet table %s not exist ", $dataSet->getId()));
            return false;
        }

        $addCols = [];
        $delCols = [];

        if ($dataSetTable->hasColumn(DataSetInterface::OVERWRITE_DATE) &&
            $dataSetTable->getColumn(DataSetInterface::OVERWRITE_DATE)->getType() != Type::getType(FieldType::DATETIME)
        ) {
            try {
                $deletedColumn = $dataSetTable->getColumn(DataSetInterface::OVERWRITE_DATE);
                $delCols[] = $deletedColumn;
                $dataSetTable->dropColumn(DataSetInterface::OVERWRITE_DATE);
            } catch (SchemaException $exception) {
                $this->logger->warning($exception->getMessage());
            }
        }

        if (!$dataSetTable->hasColumn(DataSetInterface::OVERWRITE_DATE)) {
            $addCols[] = $dataSetTable->addColumn(DataSetInterface::OVERWRITE_DATE, FieldType::DATETIME, array("notnull" => false, "default" => null));
        }

        $updateTable = new TableDiff($dataSetTable->getName(), $addCols, [], $delCols, [], [], []);

        try {
            $dataSetSynchronizer->syncSchema($schema);
            $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
            foreach ($alterSqls as $alterSql) {
                $conn->exec($alterSql);
            }
        } catch (\Exception $e) {
            $this->logger->error("Cannot Sync Schema " . $schema->getName());
        }

        $this->logger->info(sprintf('command updating override date field successfully on dataSet: ' . $dataSet->getId()));
        return true;
    }
}