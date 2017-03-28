<?php

namespace UR\Service\DataSet;

use DateTime;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
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
    public function updateDataSet(DataSetInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface){
            $this->logger->error("Data Set not exist ");
            return false;
        }

        $conn = $this->em->getConnection();
        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        if (!$dataSetTable) {
            $this->logger->error(sprintf("DataSet table %s not exist ", $dataSet->getId()));
            return false;
        }

        /** @var DataSetInterface[] $dataSetMissingUniques */
        $dataSetMissingUniques = [];

        $dimensions = $dataSet->getDimensions();
        $metrics = $dataSet->getMetrics();
        $fields = array_merge($dimensions, $metrics);

        foreach ($fields as $field => $type) {
            if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                if (
                    !in_array($dataSet, $dataSetMissingUniques) && (
                        !$dataSetTable->hasColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $field)) ||
                        !$dataSetTable->hasColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $field)) ||
                        !$dataSetTable->hasColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $field)))
                ) {
                    $dataSetMissingUniques[] = $dataSet;
                }
            }
        }

        foreach ($dataSetMissingUniques as $dataSetMissingUnique) {
            $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSetMissingUnique->getId());

            $addCols = [];

            foreach ($fields as $newColumn => $type) {
                if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                    if (!$dataSetTable->hasColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newColumn))){
                        $addCols[] = $dataSetTable->addColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                    }

                    if (!$dataSetTable->hasColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newColumn))){
                        $addCols[] = $dataSetTable->addColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                    }

                    if (!$dataSetTable->hasColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newColumn))){
                        $addCols[] = $dataSetTable->addColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
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
                foreach ($row as $index => $date) {
                    if (DateTime::createFromFormat('Y-m-d', $date)){
                        $date = DateTime::createFromFormat('Y-m-d', $date);
                    } else {
                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $date);
                    }

                    if ($date instanceof DateTime) {
                        $month = $date->format('n');
                        $year = $date->format('Y');
                        $day = $date->format('j');
                        $updateQb->update($dataSetTable->getName(), 't')->set(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $index), $day);
                        $updateQb->update($dataSetTable->getName(), 't')->set(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $index), $month);
                        $updateQb->update($dataSetTable->getName(), 't')->set(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $index), $year);
                    }
                }
                $updateQb->where('t.__id=:table_id')->setParameter(':table_id', $id);
                $updateQb->execute();
            }
        }

        $this->logger->info(sprintf('command run successfully dataSet: '.$dataSet->getId()));
        return true;
    }
}