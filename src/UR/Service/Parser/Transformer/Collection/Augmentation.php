<?php

namespace UR\Service\Parser\Transformer\Collection;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use UR\Behaviors\ParserUtilTrait;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Report\SqlBuilder;

class Augmentation implements CollectionTransformerInterface
{
    use ParserUtilTrait;

    const DATA_SOURCE_SIDE = 'leftSide';
    const MAP_DATA_SET_SIDE = 'rightSide';
    const MAP_DATA_SET = 'mapDataSet';
    const MAP_FIELDS_KEY = 'mapFields';
    const MAP_CONDITION_KEY = 'mapConditions';
    const CUSTOM_CONDITION = 'customCondition';
    const DROP_UNMATCHED = 'dropUnmatched';
    const CUSTOM_FIELD_KEY = 'field';
    const CUSTOM_OPERATOR_KEY  = 'operator';
    const CUSTOM_OPERATOR_EQUAL  = 'equal';
    const CUSTOM_OPERATOR_NOT_EQUAL  = 'notEqual';
    const CUSTOM_OPERATOR_CONTAINS  = 'contain';
    const CUSTOM_OPERATOR_NOT_CONTAINS  = 'notContain';
    const CUSTOM_VALUE_KEY = 'value';

    /**
     * @var int
     */
    protected $mapDataSet;

    /**
     * @var array
     */
    protected $selectedFields;

    /**
     * @var string
     */
    protected $sourceField;

    /**
     * @var string
     */
    protected $destinationField;

    /**
     * @var string
     */
    protected $customCondition;

    /**
    * @var array
    */
    protected $mapConditions;

    /**
     * @var bool
     */
    protected $dropUnmatched;

    /**
     * @var array
     */
    protected $mapFields;

    /**
     * Augmentation constructor.
     * @param int $mapDataSet
     * @param array $mapConditions
     * @param array $mapFields
     * @param bool $dropUnmatched
     * @param array $customCondition
     */
    public function __construct($mapDataSet, $mapConditions, array $mapFields, $dropUnmatched = false, array $customCondition = [])
    {
        $this->mapDataSet = $mapDataSet;
        $this->mapFields = $mapFields;
        $this->mapConditions = $mapConditions;
        $this->customCondition = $customCondition;
        $this->dropUnmatched = $dropUnmatched;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (count($rows) < 1) {
            return $collection;
        }
        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();
        $tableName = sprintf(SqlBuilder::DATA_SET_TABLE_NAME_TEMPLATE, $this->mapDataSet);
        $qb->from($conn->quoteIdentifier($tableName))->select('*');
        $qb->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE));

        if (is_array($this->customCondition)) {
            $mappedFields = [];
            foreach ($this->customCondition as $i => $condition) {
                if (array_key_exists(self::CUSTOM_FIELD_KEY, $condition)
                    && array_key_exists(self::CUSTOM_OPERATOR_KEY, $condition)
                    && array_key_exists(self::CUSTOM_VALUE_KEY, $condition)
                ) {
                    $field = $condition[self::CUSTOM_FIELD_KEY];
                    $paramField = sprintf(':%s_%d', $this->removeSpacesInVariableName($condition[self::CUSTOM_FIELD_KEY]), $i);
                    $operator = $condition[self::CUSTOM_OPERATOR_KEY];
                    switch ($operator) {
                        case self::CUSTOM_OPERATOR_EQUAL:
                            $qb->andWhere(sprintf('%s = %s', $conn->quoteIdentifier($field), $paramField))
                                ->setParameter($paramField, $condition[self::CUSTOM_VALUE_KEY]);
                            break;
                        case self::CUSTOM_OPERATOR_NOT_EQUAL:
                            $qb->andWhere(sprintf('%s <> %s', $conn->quoteIdentifier($field), $paramField))
                                ->setParameter($paramField, $condition[self::CUSTOM_VALUE_KEY]);
                            break;
                        case self::CUSTOM_OPERATOR_CONTAINS:
                            $qb->andWhere(sprintf('%s LIKE %s', $conn->quoteIdentifier($field), $paramField))
                                ->setParameter($paramField, sprintf('%%%s%%', $condition[self::CUSTOM_VALUE_KEY]));
                            break;
                        case self::CUSTOM_OPERATOR_NOT_CONTAINS:
                            $qb->andWhere(sprintf('%s NOT LIKE %s', $conn->quoteIdentifier($field), $paramField))
                                ->setParameter($paramField, sprintf('%%%s%%', $condition[self::CUSTOM_VALUE_KEY]));
                            break;
                        default:
                            throw new InvalidArgumentException(sprintf('operator %s is not supported', $operator));
                    }

                    $mappedFields[] = $field;
                }
            }
        }

        $mappedResult = $qb->execute()->fetchAll();

        foreach($this->mapFields as $mapField) {
            $field = $mapField[self::DATA_SOURCE_SIDE];
            if (in_array($field, $columns)) {
                continue;
            }

            $columns[] = $field;
        }


        foreach ($rows as $index=>&$row) {
            $mapFields = $this->getMappedValue($mappedResult, $row, $collection->getTypes(), $fromDateFormats, $connectedDataSource->getMapFields(), $matched);
            if ($this->dropUnmatched === true && $matched === false) {
                unset($rows[$index]);
            } else {
                $row = array_merge($row, $mapFields);
            }
        }

        unset($row);
        return new Collection($columns, array_values($rows), $types);
    }

    protected function removeSpacesInVariableName($name)
    {
        return preg_replace('/\s+/', '_', $name);
    }

    protected function getMappedValue($mappedResult, $sourceValues, $types, $dateFormats, $mapFields, &$matched)
    {
        foreach($mappedResult as $result) {
            $matched = true;
            foreach ($this->mapConditions as $mapCondition) {
                $leftField = $mapCondition[self::DATA_SOURCE_SIDE];
                $rightField = $mapCondition[self::MAP_DATA_SET_SIDE];

                if (!array_key_exists($rightField, $result)) {
                    $matched = false;
                    break;
                }

                if (!array_key_exists($leftField, $sourceValues)) {
                    $matched = false;
                    break;
                }

                $leftValue = $sourceValues[$leftField];

                $realLeftField = $leftField;
                if (array_key_exists($leftField, $mapFields)) {
                    $realLeftField = $mapFields[$leftField];
                }

                if (array_key_exists($leftField, $types) && in_array($types[$leftField], [FieldType::DATE, FieldType::DATETIME]) && array_key_exists($realLeftField, $dateFormats)) {
                    $date = $this->getDate($leftValue, $dateFormats[$realLeftField]['formats'], $dateFormats[$realLeftField]['timezone']);
                    if ($date instanceof DateTime) {
                        if ($types[$leftField] == FieldType::DATE) {
                            $leftValue = $date->format('Y-m-d');
                        } elseif ($types[$leftField] == FieldType::DATETIME) {
                            $leftValue = $date->format('Y-m-d H:i:s');
                        }
                    }
                }

                if ($result[$rightField] != $leftValue) {
                    $matched = false;
                    break;
                }
            }

            if ($matched === true) {
                $data = [];
                foreach($this->mapFields as $mapField) {
                    $data[$mapField[self::DATA_SOURCE_SIDE]] = array_key_exists($mapField[self::MAP_DATA_SET_SIDE], $result) ? $result[$mapField[self::MAP_DATA_SET_SIDE]] : NULL;
                }
                return $data;
            }
        }
        $data = [];
        foreach($this->mapFields as $mapField) {
            $data[$mapField[self::DATA_SOURCE_SIDE]] = NULL;
        }
        return $data;
    }

    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_AUGMENTATION;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return int
     */
    public function getMapDataSet(): int
    {
        return $this->mapDataSet;
    }

    /**
     * @return array
     */
    public function getSelectedFields(): array
    {
        return $this->selectedFields;
    }

    /**
     * @return string
     */
    public function getSourceField(): string
    {
        return $this->sourceField;
    }

    /**
     * @return string
     */
    public function getDestinationField(): string
    {
        return $this->destinationField;
    }

    /**
     * @return array
     */
    public function getCustomCondition()
    {
        return $this->customCondition;
    }

    /**
     * @return array
     */
    public function getMapConditions(): array
    {
        return $this->mapConditions;
    }

    /**
     * @return boolean
     */
    public function isDropUnmatched(): bool
    {
        return $this->dropUnmatched;
    }

    /**
     * @return array
     */
    public function getMapFields(): array
    {
        return $this->mapFields;
    }
}