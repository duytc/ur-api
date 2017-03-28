<?php

namespace UR\Service\Parser\Transformer;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Report\SqlBuilder;

class Augmentation implements CollectionTransformerInterface
{
    const DATA_SOURCE_SIDE = 'leftSide';
    const MAP_DATA_SET_SIDE = 'rightSide';
    const MAP_DATA_SET = 'mapDataSet';
    const MAP_FIELDS_KEY = 'mapFields';
    const MAP_CONDITION_KEY = 'mapCondition';
    const CUSTOM_CONDITION = 'customCondition';
    const DROP_UNMATCHED = 'dropUnmatched';
    const CUSTOM_FIELD_KEY = 'field';
    const CUSTOM_OPERATOR_KEY  = 'operator';
    const CUSTOM_OPERATOR_EQUAL  = 'equal';
    const CUSTOM_OPERATOR_NOT_EQUAL  = 'notEqual';
    const CUSTOM_OPERATOR_CONTAINS  = 'contain';
    const CUSTOM_OPERATOR_NOT_CONTAINS  = 'notContain';
    const CUSTOM_OPERATOR_LESS_THAN  = 'lessThan';
    const CUSTOM_OPERATOR_LESS_THAN_OR_EQUAL  = 'lessThanOrEqual';
    const CUSTOM_OPERATOR_GREATER_THAN  = 'greaterThan';
    const CUSTOM_OPERATOR_GREATER_THAN_OR_EQUAL  = 'greaterThanOrEqual';
    const CUSTOM_OPERATOR_IN  = 'in';
    const CUSTOM_OPERATOR_NOT_IN  = 'notIn';
    const CUSTOM_OPERATOR_NULL  = 'null';
    const CUSTOM_OPERATOR_NOT_NULL  = 'notNull';
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
     * @var string
     */
    protected $mapCondition;

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
     * @param string $mapCondition
     * @param array $mapFields
     * @param bool $dropUnmatched
     * @param array $customCondition
     */
    public function __construct($mapDataSet, $mapCondition, array $mapFields, $dropUnmatched = false, array $customCondition = [])
    {
        $this->mapDataSet = $mapDataSet;
        $this->mapFields = $mapFields;
        $this->sourceField = $mapCondition[self::DATA_SOURCE_SIDE];
        $this->destinationField = $mapCondition[self::MAP_DATA_SET_SIDE];
        $this->customCondition = $customCondition;
        $this->dropUnmatched = $dropUnmatched;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (count($rows) < 1) {
            return $collection;
        }
        $qb = $em->getConnection()->createQueryBuilder();
        $tableName = sprintf(SqlBuilder::DATA_SET_TABLE_NAME_TEMPLATE, $this->mapDataSet);
        $qb->from($tableName)->select('*');
        $columnTypes = $em->getConnection()->getSchemaManager()->listTableColumns($tableName);
        $mapDataSetTypes = [];

        /** @var Column $column */
        foreach($columnTypes as $column) {
            $mapDataSetTypes[$column->getName()] = $column->getType()->getName();
        }

        if (is_array($this->customCondition)) {
            $mappedFields = [];
            foreach($this->customCondition as $condition) {
                if (array_key_exists(self::CUSTOM_FIELD_KEY, $condition)
                    && array_key_exists(self::CUSTOM_OPERATOR_KEY, $condition)
                    && array_key_exists(self::CUSTOM_VALUE_KEY, $condition)
                ) {
                    $field = $condition[self::CUSTOM_FIELD_KEY];
                    switch($condition[self::CUSTOM_OPERATOR_KEY]) {
                        case self::CUSTOM_OPERATOR_EQUAL:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s = "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s = %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_NOT_EQUAL:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s <> "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s <> %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_CONTAINS:
                            $qb->andWhere(sprintf('%s LIKE "%%%s%%"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            break;
                        case self::CUSTOM_OPERATOR_NOT_CONTAINS:
                            $qb->andWhere(sprintf('%s NOT LIKE "%%%s%%"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            break;
                        case self::CUSTOM_OPERATOR_LESS_THAN:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s < "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s < %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_LESS_THAN_OR_EQUAL:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s <= "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s <= %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_GREATER_THAN:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s > "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s > %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_GREATER_THAN_OR_EQUAL:
                            if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                $qb->andWhere(sprintf('%s >= "%s"', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            } else {
                                $qb->andWhere(sprintf('%s >= %d', $condition[self::CUSTOM_FIELD_KEY], $condition[self::CUSTOM_VALUE_KEY]));
                            }
                            break;
                        case self::CUSTOM_OPERATOR_IN:
                            $values = explode(',', $condition[self::CUSTOM_VALUE_KEY]);
                            foreach($values as &$value) {
                                if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                    $value = sprintf('"%s"', $value);
                                }
                            }
                            $expr = $qb->expr()->in($condition[self::CUSTOM_FIELD_KEY], $values);
                            $qb->andWhere($expr);
                            break;
                        case self::CUSTOM_OPERATOR_NOT_IN:
                            $values = explode(',', $condition[self::CUSTOM_VALUE_KEY]);
                            foreach($values as &$value) {
                                if (in_array($mapDataSetTypes[$field], ['datetime', 'text'])) {
                                    $value = sprintf('"%s"', $value);
                                }
                            }
                            $expr = $qb->expr()->notIn($condition[self::CUSTOM_FIELD_KEY], $values);
                            $qb->andWhere($expr);
                            break;
                        case self::CUSTOM_OPERATOR_NULL:
                            $expr = $qb->expr()->isNull($condition[self::CUSTOM_FIELD_KEY]);
                            $qb->andWhere($expr);
                            break;
                        case self::CUSTOM_OPERATOR_NOT_NULL:
                            $expr = $qb->expr()->isNotNull($condition[self::CUSTOM_FIELD_KEY]);
                            $qb->andWhere($expr);
                            break;
                        default:
                            throw new InvalidArgumentException(sprintf('operator %s is not supported', $condition[self::CUSTOM_OPERATOR_KEY]));
                    }
                    $mappedFields[] = $field;
                }
            }

            $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
            $linkedMapDataSetRepository->override($this->mapDataSet, $connectedDataSource, $mappedFields);
        }

        $mappedResult = $qb->execute()->fetchAll();

        foreach($this->mapFields as $mapField) {
            $field = $mapField[self::DATA_SOURCE_SIDE];
            if (in_array($field, $columns)) {
                continue;
            }

            $columns[] = $field;
        }


        foreach ($rows as $i=>&$row) {
            if (!array_key_exists($this->sourceField, $row)) {
                continue;
            }
            $mapFields = $this->getMappedValue($mappedResult, $row[$this->sourceField], $matched);
            if ($this->dropUnmatched === true && $matched === false) {
                unset($rows[$i]);
            } else {
                $row = array_merge($row, $mapFields);
            }
        }

        return new Collection($columns, array_values($rows), $types);
    }

    protected function getMappedValue($mappedResult, $sourceValue, &$matched)
    {
        $matched = false;
        foreach($mappedResult as $result) {
            if (array_key_exists($this->destinationField, $result) && $result[$this->destinationField] == $sourceValue) {
                $data = [];
                foreach($this->mapFields as $mapField) {
                    $data[$mapField[self::DATA_SOURCE_SIDE]] = array_key_exists($mapField[self::MAP_DATA_SET_SIDE], $result) ? $result[$mapField[self::MAP_DATA_SET_SIDE]] : NULL;
                }

                $matched = true;
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
     * @return string
     */
    public function getMapCondition(): string
    {
        return $this->mapCondition;
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