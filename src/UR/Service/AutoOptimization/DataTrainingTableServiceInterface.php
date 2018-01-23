<?php

namespace UR\Service\AutoOptimization;


use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Exception;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;

interface DataTrainingTableServiceInterface
{
    /**
     *
     * Synchronize the schema with the database
     *
     * @param Schema $schema
     * @return $this
     * @throws DBALException
     */
    public function syncSchema(Schema $schema);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return Table|false
     */
    public function createEmptyDataTrainingTable(AutoOptimizationConfigInterface $autoOptimizationConfig);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $params
     * @return array
     */
    public function getIdentifiersForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig, $params);

    /**
     * @param ReportResultInterface $collection
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return Collection
     * @throws Exception
     */
    public function importDataToDataTrainingTable(ReportResultInterface $collection, AutoOptimizationConfigInterface $autoOptimizationConfig);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifiers
     * @return ReportResultInterface
     */
    public function getDataByIdentifiers(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $columnName
     * @return mixed
     */
    public function getAllValuesOfOneColumn(AutoOptimizationConfigInterface $autoOptimizationConfig, $columnName);

}