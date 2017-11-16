<?php


namespace UR\Service\DataSet;


use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SplDoublyLinkedList;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Params;
use UR\DomainManager\DataSetManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\PublicSimpleException;
use UR\Service\Report\SqlBuilder;
use UR\Service\StringUtilTrait;
use UR\Worker\Manager;

class DataMappingService implements DataMappingServiceInterface
{
    use StringUtilTrait;
    const LEFT_SIDE_KEY = 'leftSide';
    const RIGHT_SIDE_KEY = 'rightSide';

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /**
     * @var SqlBuilder
     */
    protected $sqlBuilder;

    protected $keys = [
        DataSetInterface::ENTRY_DATE_COLUMN,
        DataSetInterface::DATA_SOURCE_ID_COLUMN,
        DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN,
        DataSetInterface::IMPORT_ID_COLUMN,
    ];

    /** @var Manager */
    protected $manager;

    /** @var AugmentationMappingService */
    protected $augmentationMappingService;

    /**
     * DataMappingService constructor.
     * @param EntityManagerInterface $em
     * @param DataSetManagerInterface $dataSetManager
     * @param SqlBuilder $sqlBuilder
     * @param Manager $manager
     * @param AugmentationMappingService $augmentationMappingService
     */
    public function __construct(EntityManagerInterface $em, DataSetManagerInterface $dataSetManager, SqlBuilder $sqlBuilder, Manager $manager, AugmentationMappingService $augmentationMappingService)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->dataSetManager = $dataSetManager;
        $this->sqlBuilder = $sqlBuilder;
        $this->manager = $manager;
        $this->augmentationMappingService = $augmentationMappingService;
    }


    /**
     * @param $dataSetId
     * @return bool
     */
    public function importDataFromMapBuilderConfig($dataSetId)
    {
        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return false;
        }

        /** @var MapBuilderConfigInterface $config */
        foreach ($dataSet->getMapBuilderConfigs() as $config) {
            $dataSetRows = $this->getDataFromMapBuilderConfig($config);
            $this->insertRowsToDataSet($config, $dataSetRows);
        }

        $dataSet->setNumChanges(0);
        $this->em->merge($dataSet);
        $this->em->flush();

        return true;
    }

    /**
     * @param MapBuilderConfigInterface $mapBuilderConfig
     * @return SplDoublyLinkedList
     */
    protected function getDataFromMapBuilderConfig(MapBuilderConfigInterface $mapBuilderConfig)
    {
        $data = array(
            DataSet::DATA_SET_ID_KEY => $mapBuilderConfig->getMapDataSet()->getId(),
            DataSet::DIMENSIONS_KEY => array_merge($mapBuilderConfig->getMapFields(), $this->keys, [DataSetInterface::UNIQUE_ID_COLUMN => DataSetInterface::UNIQUE_ID_COLUMN]),
            DataSet::METRICS_KEY => [],
            DataSet::FILTERS_KEY => []
        );

        $dataSetDTO = new DataSet($data);
        $params = new Params();
        $params->setDataSets([$dataSetDTO]);
        $result = $this->sqlBuilder->buildQueryForSingleDataSet($params);

        if (array_key_exists(SqlBuilder::ROWS, $result)) {
            $rows = $result[SqlBuilder::ROWS];
        } else {
            /** @var Statement $stmt */
            $stmt = $result[SqlBuilder::STATEMENT_KEY];
            try {
                $stmt->execute();
            } catch (\Exception $ex) {

            }

            $rows = new SplDoublyLinkedList();
            while ($row = $stmt->fetch()) {
                $rows->push($row);
            }
        }

        $this->sqlBuilder->removeTemporaryTables($params);
        gc_collect_cycles();
        
        return $rows;
    }

    public function mapTags(DataSetInterface $dataSet, array $params)
    {
        if (!array_key_exists(self::LEFT_SIDE_KEY, $params) || !array_key_exists(self::RIGHT_SIDE_KEY, $params)) {
            throw new InvalidArgumentException('invalid mapping params!');
        }
        $leftSide = $params[self::LEFT_SIDE_KEY];
        $rightSide = $params[self::RIGHT_SIDE_KEY];

        if (is_array($leftSide) && is_array($rightSide)) {
            throw new InvalidArgumentException('invalid mapping params!');
        }

        $isLeft = true;
        if (is_array($leftSide)) {
            $isLeft = false;
            $referenceUnique = $rightSide;
            $updateUniques = $leftSide;
        } else {
            $referenceUnique = $leftSide;
            $rightSide = is_array($rightSide) ? $rightSide : [$rightSide];
            $updateUniques = $rightSide;
        }

        $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId());

        $mapSideConfigs = $dataSet->getMapBuilderConfigs();

        foreach ($updateUniques as $item) {
            if ($item['__is_associated'] == true) {
                /** @var MapBuilderConfigInterface $mapSideConfig */
                foreach ($mapSideConfigs as $mapSideConfig) {
                    if ($isLeft != $mapSideConfig->isLeftSide()) {
                        continue;
                    }
                    $columns = array_keys($mapSideConfig->getMapFields());

                    $qb = $this->conn->createQueryBuilder();
                    $qb->from($this->conn->quoteIdentifier($tableName))
                        ->where(sprintf('%s = :uniqueId', $this->conn->quoteIdentifier('__unique_id')))
                        ->setParameter('uniqueId', $referenceUnique);
                    foreach ($columns as $column) {
                        $qb->addSelect($column);
                    }
                    $referenceData = $qb->execute()->fetchAll();

                    if (count($referenceData) < 1) {
                        continue;
                    }

                    $referenceData = reset($referenceData);

                    // get data to duplicate
                    $qb = $this->conn->createQueryBuilder();
                    $qb->select("*")->from($this->conn->quoteIdentifier($tableName))
                        ->where(sprintf('%s = :uniqueId', $this->conn->quoteIdentifier('__unique_id')))
                        ->setParameter('uniqueId', $item[DataSetInterface::UNIQUE_ID_COLUMN]);
                    $qb->andWhere(sprintf('%s is null', $this->conn->quoteIdentifier('__overwrite_date')));
                    $insertIntoData = $qb->execute()->fetchAll();

                    if (count($insertIntoData) < 1) {
                        continue;
                    }

                    $insertIntoData = reset($insertIntoData);

                    // reference Data according Map field
                    foreach ($columns as $column) {
                        $insertIntoData[$column] = $referenceData[$column];
                    }

                    // Remove __id
                    $insertIntoData[DataSetInterface::ID_COLUMN] = '';

                    //associate rows
                    $qb = $this->conn->createQueryBuilder();
                    $qb->insert($tableName);

                    foreach ($insertIntoData as $key => $value) {
                        if ($value == null || $value == '') {
                            continue;
                        }
                        $qb
                            ->setValue($key, ":$key")
                            ->setParameter(":$key", $value);
                    }

                    $qb->execute();
                }
            } else {
                /** @var MapBuilderConfigInterface $mapSideConfig */
                foreach ($mapSideConfigs as $mapSideConfig) {
                    if ($isLeft != $mapSideConfig->isLeftSide()) {
                        continue;
                    }
                    $columns = array_keys($mapSideConfig->getMapFields());

                    $qb = $this->conn->createQueryBuilder();
                    $qb->from($this->conn->quoteIdentifier($tableName))
                        ->where(sprintf('%s = :uniqueId', $this->conn->quoteIdentifier(DataSetInterface::UNIQUE_ID_COLUMN)))
                        ->setParameter('uniqueId', $referenceUnique);
                    foreach ($columns as $column) {
                        $qb->addSelect($column);
                    }
                    $referenceData = $qb->execute()->fetchAll();

                    if (count($referenceData) < 1) {
                        continue;
                    }

                    $referenceData = reset($referenceData);

                    //associate rows
                    $qb = $this->conn->createQueryBuilder();
                    $qb
                        ->update($tableName)
                        ->where(sprintf('%s = :uniqueId', $this->conn->quoteIdentifier(DataSetInterface::UNIQUE_ID_COLUMN)))
                        ->setParameter('uniqueId', $item[DataSetInterface::UNIQUE_ID_COLUMN]);

                    foreach ($columns as $column) {
                        $value = $referenceData[$column];
                        if ($value == null || $value == '') {
                            continue;
                        }
                        $qb
                            ->set($column, ":$column")
                            ->setParameter(":$column", $value);
                    }
                    $qb->set(DataSetInterface::MAPPING_IS_ASSOCIATED, 1);
                    $qb->set(DataSetInterface::MAPPING_IS_MAPPED, 1);
                    $qb->execute();
                }
            }
        }

        //set is_mapped left side rows
        $qb = $this->conn->createQueryBuilder();
        $qb->update($tableName)
            ->where('__unique_id= :referenceUnique')
            ->setParameter('referenceUnique', $referenceUnique);

        $qb->set(DataSetInterface::MAPPING_IS_MAPPED, 1);
        $qb->execute();

        $this->augmentationMappingService->noticeChangesInDataSetMapBuilder($dataSet, $this->em);
    }

    /**
     * @param DataSetInterface $dataSet
     * @param $rowId
     * @param $isLeftSide
     */
    public function unMapTags(DataSetInterface $dataSet, $rowId, $isLeftSide)
    {
        $mapSideConfigs = $dataSet->getMapBuilderConfigs();
        if ($mapSideConfigs instanceof Collection) {
            $mapSideConfigs = $mapSideConfigs->toArray();
        }

        $columns = [];
        $columnsOpposite = [];
        /** @var MapBuilderConfigInterface $mapSideConfig */
        foreach ($mapSideConfigs as $mapSideConfig) {
            if ($isLeftSide != $mapSideConfig->isLeftSide()) {
                $columns = array_merge($columns, array_keys($mapSideConfig->getMapFields()));
            } else {
                $columnsOpposite = array_merge($columnsOpposite, array_keys($mapSideConfig->getMapFields()));
            }
        }

        try {
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId());
            $qb = $this->conn->createQueryBuilder();

            // get item need to unmapped
            $qb->select("*")->from($tableName)->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId))->execute();
            $item =  $qb->execute()->fetch();

            // get all rows have been mapped contains this $columns and $isLeftSide value
            $qb = $this->conn->createQueryBuilder();
            $qb->select("*")->from($tableName);

            foreach ($columnsOpposite as $column) {
                $value = $item[$column];
                if ($value == null || $value == '') {
                    continue;
                }
                $qb
                    ->andWhere($column. "= :$column")
                    ->setParameter(":$column", $value);
            }
            $qb->andWhere($qb->expr()->eq(DataSetInterface::MAPPING_IS_ASSOCIATED, 1));
            $qb->andWhere($qb->expr()->eq(DataSetInterface::MAPPING_IS_LEFT_SIDE, $isLeftSide));
            $qb->andWhere(sprintf('%s is null', $this->conn->quoteIdentifier('__overwrite_date')));
            $itemsFound = $qb->execute()->fetchAll();

            if (count($itemsFound) == 1) {
                // remove opposite side value to null
                // set is_associated to false
                $qb = $this->conn->createQueryBuilder();
                $qb->update($tableName)
                    ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId));

                foreach ($columns as $field) {
                    $qb
                        ->set($field, ":value")
                        ->setParameter(':value', null);
                }

                $qb->set(DataSetInterface::MAPPING_IS_ASSOCIATED, 0);
                //$qb->set(DataSetInterface::MAPPING_IS_MAPPED, 0);

                $qb->execute();

            } else {

                //delete this item because there is one or many another item has the same $columns and $isLeftSide value
                $qb = $this->conn->createQueryBuilder();
                $qb->delete($tableName)
                    ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId));
                $qb->execute();

            }

            //get row according map columns
            $qb = $this->conn->createQueryBuilder();
            $qb->select("*")->from($tableName);

            foreach ($columnsOpposite as $column) {
                $value = $item[$column];
                if ($value == null || $value == '') {
                    continue;
                }
                $qb
                    ->andWhere($column. "= :$column")
                    ->setParameter(":$column", $value);
            }

            $qb->andWhere(sprintf('%s is null', $this->conn->quoteIdentifier('__overwrite_date')));

            $itemsFound = $qb->execute()->fetchAll();

            if (count($itemsFound) == 1) {
                //update // remove leftSide value and set is_associated to false
                if ($itemsFound[0][DataSetInterface::MAPPING_IS_ASSOCIATED] != 1){
                    $qb = $this->conn->createQueryBuilder();
                    $qb->update($tableName)
                        ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $itemsFound[0][DataSetInterface::ID_COLUMN]));

                    $qb->set(DataSetInterface::MAPPING_IS_MAPPED, 0);

                    $qb->execute();

                }
            }

            $qb = $this->conn->createQueryBuilder();
            $qb->select("*")->from($tableName);

            foreach ($columns as $column) {
                $value = $item[$column];
                if ($value == null || $value == '') {
                    continue;
                }
                $qb
                    ->andWhere($column. "= :$column")
                    ->setParameter(":$column", $value);
            }

            $qb->andWhere(sprintf('%s is null', $this->conn->quoteIdentifier('__overwrite_date')));

            $itemsOppositeFound = $qb->execute()->fetchAll();

            if (count($itemsOppositeFound) == 1) {
                //update // remove leftSide value and set is_associated to false
                if ($itemsOppositeFound[0][DataSetInterface::MAPPING_IS_ASSOCIATED] != 1){
                    $qb = $this->conn->createQueryBuilder();
                    $qb->update($tableName)
                        ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $itemsOppositeFound[0][DataSetInterface::ID_COLUMN]));

                    $qb->set(DataSetInterface::MAPPING_IS_MAPPED, 0);

                    $qb->execute();

                }
            }

            $this->augmentationMappingService->noticeChangesInDataSetMapBuilder($dataSet, $this->em);

        } catch (Exception $e) {

        }
    }

    public function oldUnMapTags(DataSetInterface $dataSet, $rowId, $isLeftSide)
    {
        $mapSideConfigs = $dataSet->getMapBuilderConfigs();
        if ($mapSideConfigs instanceof Collection) {
            $mapSideConfigs = $mapSideConfigs->toArray();
        }

        $columns = [];
        $columnsOpposite = [];
        /** @var MapBuilderConfigInterface $mapSideConfig */
        foreach ($mapSideConfigs as $mapSideConfig) {
            if ($isLeftSide != $mapSideConfig->isLeftSide()) {
                $columns = array_merge($columns, array_keys($mapSideConfig->getMapFields()));
            } else {
                $columnsOpposite = array_merge($columnsOpposite, array_keys($mapSideConfig->getMapFields()));
            }
        }

        try {
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId());
            $qb = $this->conn->createQueryBuilder();

            // get item need to unmapped
            $qb->select("*")->from($tableName)->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId))->execute();
            $item =  $qb->execute()->fetch();

            // get all rows have been mapped contains this columns
            $qb = $this->conn->createQueryBuilder();
            $qb->select("*")->from($tableName);

            foreach ($columns as $column) {
                $value = $item[$column];
                if ($value == null || $value == '') {
                    continue;
                }
                $qb
                    ->andWhere($column. "= :$column")
                    ->setParameter(":$column", $value);
            }
            $qb->andWhere($qb->expr()->eq(DataSetInterface::MAPPING_IS_ASSOCIATED, 1));
            $itemsFound = $qb->execute()->fetchAll();

            if (count($itemsFound) == 1) {
                // set is_mapped of left side to false
                $qb = $this->conn->createQueryBuilder();
                $qb->update($tableName);
                foreach ($columns as $column) {
                    $value = $item[$column];
                    if ($value == null || $value == '') {
                        continue;
                    }
                    $qb
                        ->andWhere($column. "= :$column")
                        ->setParameter(":$column", $value);

                }
                $qb->set(DataSetInterface::MAPPING_IS_MAPPED, ":value")
                    ->setParameter(':value', 0);
                $qb->execute();
            }

            // remove leftSide value and set is_associated to false
            // get all rows have been mapped contains this rightSide
            $qb = $this->conn->createQueryBuilder();
            $qb->select("*")->from($tableName);

            foreach ($columnsOpposite as $column) {
                $value = $item[$column];
                if ($value == null || $value == '') {
                    continue;
                }
                $qb
                    ->andWhere($column. "= :$column")
                    ->setParameter(":$column", $value);
            }
            $itemsOppositeFound = $qb->execute()->fetchAll();

            if (count($itemsOppositeFound) == 1) {
                //update // remove leftSide value and set is_associated to false
                $qb = $this->conn->createQueryBuilder();
                $qb->update($tableName)
                    ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId));

                foreach ($columns as $field) {
                    $qb
                        ->set($field, ":value")
                        ->setParameter(':value', null);
                }

                $qb->set(DataSetInterface::MAPPING_IS_ASSOCIATED, 0);
                $qb->set(DataSetInterface::MAPPING_IS_MAPPED, 0);

                $qb->execute();
            } else {

                // delete this item
                $qb = $this->conn->createQueryBuilder();
                $qb->delete($tableName)
                    ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId));

                $qb->execute();
            }

            $this->augmentationMappingService->noticeChangesInDataSetMapBuilder($dataSet, $this->em);
        } catch (Exception $e) {

        }
    }

    /**
     * @param MapBuilderConfigInterface $config
     * @param SplDoublyLinkedList $dataSetRows
     * @param $showDataSetId
     */
    private function insertRowsToDataSet(MapBuilderConfigInterface $config, SplDoublyLinkedList $dataSetRows, $showDataSetId = true)
    {
        $dataSet = $config->getDataSet();
        $mapDataSet = $config->getMapDataSet();

        $fieldsToInsert = [];
        $values = [];

        foreach ($config->getMapFields() as $key => $field) {
            $fieldsToInsert[$this->getStandardName($key)] = $showDataSetId ? sprintf('%s_%d', $field, $mapDataSet->getId()) : $field;
            $values[$this->conn->quoteIdentifier($this->getStandardName($key))] = '?';
        }

        $defaultKeys = [];
        foreach ($this->keys as $key) {
            $defaultKeys[$key] = $showDataSetId ? sprintf('%s_%d', $key, $mapDataSet->getId()) : $key;
            $values[$this->conn->quoteIdentifier($key)] = '?';
        }

        $values[$this->conn->quoteIdentifier(DataSetInterface::MAPPING_IS_LEFT_SIDE)] = '?';
        $values[$this->conn->quoteIdentifier(DataSetInterface::MAPPING_IS_ASSOCIATED)] = '?';
        $values[$this->conn->quoteIdentifier(DataSetInterface::MAPPING_IS_IGNORED)] = '?';
        $values[$this->conn->quoteIdentifier(DataSetInterface::UNIQUE_ID_COLUMN)] = '?';
        $qb = $this->conn->createQueryBuilder();
        $qb->insert($this->conn->quoteIdentifier(sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId())));

        foreach ($dataSetRows as $dataSetRow) {
            $qb->values($values);
            $index = 0;
            $validData = false;
            foreach ($fieldsToInsert as $key => $field) {
                $qb->setParameter($index, isset($dataSetRow[$field]) ? $dataSetRow[$field] : null);
                $index++;
                if (isset($dataSetRow[$field]) && $dataSetRow[$field] != null) {
                    $validData = true;
                }
            }

            if (!$validData) {
                continue;
            }

            foreach ($defaultKeys as $key => $field) {
                $qb->setParameter($index, isset($dataSetRow[$field]) ? $dataSetRow[$field] : null);
                $index++;
            }
            $qb->setParameter($index, $config->isLeftSide());
            $index++;
            $qb->setParameter($index, false);
            $index++;
            $qb->setParameter($index, false);
            $index++;
            $qb->setParameter($index, $this->calculateUniqueId($config, $dataSetRow));

            if ($this->checkIfDataExist($fieldsToInsert, $dataSetRow, $dataSet)) {
                continue;
            }

            try {
                $qb->execute();
            } catch (Exception $e) {
                $e->getMessage();
            }

            unset($dataSetRow);
            gc_collect_cycles();
        }

        unset($dataSetRows);
        gc_collect_cycles();
    }

    /**
     * @inheritdoc
     */
    public function importDataFromComponentDataSet(MapBuilderConfigInterface $config, \UR\Service\DTO\Collection $collection)
    {
        $this->insertRowsToDataSet($config, $collection->getRows(), false);
        $this->manager->updateOverwriteDateForDataSet($config->getDataSet()->getId());
        $this->manager->updateTotalRowsForDataSet($config->getDataSet()->getId());
    }

    /**
     * @param array $fieldToInsert
     * @param array $row
     * @param DataSetInterface $dataSet
     * @return bool
     * @internal param QueryBuilder $qb
     */
    private function checkIfDataExist(array $fieldToInsert, array $row, DataSetInterface $dataSet)
    {
        $qb = $this->conn->createQueryBuilder();
        $qb->from($this->conn->quoteIdentifier(sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId())));
        $qb->select('__id');
        foreach ($fieldToInsert as $fieldInDataBase => $fieldInRow) {
            if (!array_key_exists($fieldInRow, $row) || $row[$fieldInRow] == null || $row[$fieldInRow] == "") {
                continue;
            }
            $field = $this->conn->quoteIdentifier($fieldInDataBase);
            $value = '"' . $row[$fieldInRow] . '"';
            $qb
                ->andWhere(" $field = $value");
        }

        try {
            if ($qb->execute()->rowCount() > 0) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function validateMapBuilderConfigs(DataSetInterface $dataSet)
    {
        $mapBuilderConfigs = $dataSet->getMapBuilderConfigs();

        if ($mapBuilderConfigs instanceof Collection) {
            $mapBuilderConfigs = $mapBuilderConfigs->toArray();
        }

        $isLeft = false;
        $isRight = false;

        foreach ($mapBuilderConfigs as $mapBuilderConfig) {
            if (!$mapBuilderConfig instanceof MapBuilderConfigInterface) {
                continue;
            }
            if ($mapBuilderConfig->isLeftSide()) {
                $isLeft = true;
            }

            if (!$mapBuilderConfig->isLeftSide()) {
                $isRight = true;
            }

            if (count($mapBuilderConfig->getMapFields()) < 1) {
                throw New PublicSimpleException(sprintf('MapFields for Map Builder empty, please check on data set %s, id %s', $dataSet->getName(), $dataSet->getId()));
            }
        }

        if (!$isLeft || !$isRight) {
            throw New PublicSimpleException(sprintf('Map Builder need at least 1 leftSide and 1 rightSide, please check on data set %s, id %s', $dataSet->getName(), $dataSet->getId()));
        }

        return true;
    }

    /**
     * @param MapBuilderConfigInterface $config
     * @param array $dataSetRow
     * @return string
     */
    private function calculateUniqueId(MapBuilderConfigInterface $config, array $dataSetRow)
    {
        $dataSet = $config->getDataSet();
        $dimensions = $dataSet->getDimensions();
        $mapFields = $config->getMapFields();

        $fieldToCalculateUniqueId = [];
        foreach ($dimensions as $dimension => $fieldType) {
            if (!array_key_exists($dimension, $mapFields)) {
                continue;
            }

            $map = $this->removeIdSuffix($mapFields[$dimension]);
            $mapWithId = sprintf('%s_%s', $map, $config->getMapDataSet()->getId());
            if (!array_key_exists($mapWithId, $dataSetRow)) {
                continue;
            }

            $fieldToCalculateUniqueId[] = $dataSetRow[$mapWithId];
        }

        return md5(implode(':', $fieldToCalculateUniqueId));
    }
}