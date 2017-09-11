<?php


namespace UR\Service;


use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use PDO;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\NumberFilterInterface;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Domain\DTO\Report\Filters\TextFilterInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfig;
use UR\Domain\DTO\Report\JoinBy\JoinField;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Exception\RuntimeException;
use UR\Service\DataSet\FieldType;

trait SqlUtilTrait
{
    use StringUtilTrait;

    /**
     * @param QueryBuilder $qb
     * @param $transforms
     * @param array $types
     * @param $removeSuffix
     * @return QueryBuilder
     */
    public function addGroupByQuery(QueryBuilder $qb, $transforms, array $types, $removeSuffix = false)
    {
        if (!is_array($transforms) || count($transforms) < 1) {
            return $qb;
        }

        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $timezone = $transform->getTimezone();
                $fields = $transform->getFields();
                $fields = array_map(function($field) use ($types, $timezone, $removeSuffix) {
                    if (array_key_exists($field, $types) && $types[$field] == FieldType::DATETIME) {
                        if ($removeSuffix === true) {
                            $field = $this->removeIdSuffix($field);
                        }

                        if ($timezone != 'UTC') {
                            return sprintf("DATE(CONVERT_TZ(`$field``, 'UTC', '$timezone'))");
                        }

                        return sprintf("DATE(`$field`)");
                    }

                    $field = $removeSuffix === true ? $this->removeIdSuffix($field) : $field;
                    return "`$field`";
                }, $fields);
                $qb->addGroupBy($fields);
                return $qb;
            }
        }

        return $qb;
    }

    /**
     * @param $sql
     * @param $transforms
     * @param array $types
     * @param array $dataSetIndexes
     * @param $forGrouper
     * @param array $joinConfigs
     * @return string
     */
    public function generateGroupByQuery($sql, $transforms, array $types = [], $dataSetIndexes = [], $forGrouper = false, $joinConfigs = [])
    {
        if (!is_array($transforms) || count($transforms) < 1) {
            return $sql;
        }

        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $timezone = $transform->getTimezone();
                $fields = $transform->getFields();
                foreach ($fields as &$field) {
                    if ($forGrouper) {
                        $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfigs, $dataSetIndexes);
                        if ($alias) {
                            $field = $alias;
                            continue;
                        } else {
                            $idAndField = $this->getIdSuffixAndField($field);
                            if ($idAndField) {
                                if (array_key_exists($idAndField['id'], $dataSetIndexes)) {
                                    if (array_key_exists($field, $types) && $types[$field] == FieldType::DATETIME) {
                                        if ($timezone != 'UTC') {
                                            $field = sprintf("DATE(CONVERT_TZ(t%d.%s, 'UTC', '$timezone'))", $dataSetIndexes[$idAndField['id']], $idAndField['field']);
                                        } else $field = sprintf("DATE(t%d.%s)", $dataSetIndexes[$idAndField['id']], $idAndField['field']);
                                        continue;
                                    }
                                    $field = sprintf('t%d.%s', $dataSetIndexes[$idAndField['id']], $idAndField['field']);
                                    continue;
                                }
                            }
                        }
                    }

                    if (array_key_exists($field, $types) && $types[$field] == FieldType::DATETIME) {
                        if ($timezone != 'UTC') {
                            $field = sprintf("DATE(CONVERT_TZ(`$field`, 'UTC', '$timezone'))");
                        } else $field = sprintf("DATE(`$field`)");
                        continue;
                    }

                    if (!$forGrouper) {
                        $field = "`$field`";
                    }
                }

                unset($field);
                $sql = sprintf('%s GROUP BY %s', $sql, implode(',', $fields));
                return $sql;
            }
        }

        return $sql;
    }

    public function convertOutputJoinFieldToAlias($field, array $joinConfigs, array $dataSetIndexes)
    {
        /** @var JoinConfig $joinConfig */
        foreach ($joinConfigs as $joinConfig) {
            $outputJoinFields = explode(',', $joinConfig->getOutputField());
            foreach ($outputJoinFields as $index => $outputJoinField) {
                if ($field == $outputJoinField) {
                    $joinField = $joinConfig->getJoinFields()[0];
                    if (array_key_exists($joinField->getDataSet(), $dataSetIndexes)) {
                        $inputJoinFields = explode(',', $joinField->getField());
                        if (isset($inputJoinFields[$index])) {
                            return sprintf('t%d.%s', $dataSetIndexes[$joinField->getDataSet()], $inputJoinFields[$index]);
                        }
                    }
                }
            }
        }

        return null;
    }

    public function convertOutputJoinField($field, array $joinConfigs)
    {
        /** @var JoinConfig $joinConfig */
        foreach ($joinConfigs as $joinConfig) {
            foreach (explode(',', $joinConfig->getOutputField()) as $i => $v) {
                if ($field == $v) {
                    $joinField = $joinConfig->getJoinFields()[0];
                    return sprintf('%s_%d', explode(',', $joinField->getField())[$i], $joinField->getDataSet());
                }
            }
            if (in_array($field, explode(',', $joinConfig->getOutputField()))) {
                $joinField = $joinConfig->getJoinFields()[0];
                return sprintf('%s_%d', $joinField->getField(), $joinField->getDataSet());
            }
        }

        return null;
    }

    /**
     * @param QueryBuilder $qb
     * @param $page
     * @param $limit
     * @return QueryBuilder
     */
    public function addLimitQuery(QueryBuilder $qb, $page, $limit)
    {
        if (!is_int($page) || !is_int($limit)) {
            return $qb;
        }

        if ($page < 1 || $limit < 1) {
            return $qb;
        }

        $offset = ($page - 1) * $limit;
        $qb->setMaxResults($limit)->setFirstResult($offset);

        return $qb;
    }

    /**
     * @param $sql
     * @param $page
     * @param $limit
     * @return string
     */
    public function generateLimitQuery($sql, $page, $limit)
    {
        if (!is_int($page) || !is_int($limit)) {
            return $sql;
        }

        if ($page < 1 || $limit < 1) {
            return $sql;
        }

        $offset = ($page - 1) * $limit;

        return sprintf('%s LIMIT %d OFFSET %d', $sql, $limit, $offset);
    }

    /**
     * @param QueryBuilder $qb
     * @param array $transforms
     * @param null $sortField
     * @param null $orderBy
     * @return QueryBuilder
     */
    public function addSortQuery(QueryBuilder $qb, $transforms = [], $sortField = null, $orderBy = null)
    {
        $sortFields = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof SortByTransform) {
                $sortObjects = $transform->getSortObjects();
                foreach ($sortObjects as $sortObject) {
                    $names = $sortObject[SortByTransform::FIELDS_KEY];
                    if (!empty($names)) {
                        foreach ($names as $name) {
                            $qb->addOrderBy("`$name`", $sortObject[SortByTransform::SORT_DIRECTION_KEY]);
                            if (!in_array($name, $sortFields)) {
                                $sortFields[] = $name;
                            }
                        }
                    }
                }
            }
        }

        if (empty($sortField) || empty($orderBy)) {
            return $qb;
        }

        if (!in_array(strtolower($orderBy), ['asc', 'desc'])) {
            return $qb;
        }

        $sortField = str_replace('"', '', $sortField);
        if (in_array($sortField, $sortFields)) {
            return $qb;
        }

        $qb->addOrderBy("`$sortField`", $orderBy);

        return $qb;
    }

    /**
     * @param $sql
     * @param array $transforms
     * @param null $sortField
     * @param null $orderBy
     * @return string
     */
    public function generateSortQuery($sql, $transforms = [], $sortField = null, $orderBy = null)
    {
        $sortQuery = [];
        $sortFields = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof SortByTransform) {
                $sortObjects = $transform->getSortObjects();
                foreach ($sortObjects as $sortObject) {
                    $names = $sortObject[SortByTransform::FIELDS_KEY];
                    if (!empty($names)) {
                        foreach ($names as $name) {
                            $sortQuery[] = sprintf('`%s` %s', $name, $sortObject[SortByTransform::SORT_DIRECTION_KEY]);
                            if (!in_array($name, $sortFields)) {
                                $sortFields[] = $name;
                            }
                        }
                    }
                }
            }
        }

        if (!empty($sortField) && !empty($orderBy) && !in_array($sortField, $sortFields)) {
            $sortField = str_replace('"', '', $sortField);
            $sortQuery[] = sprintf('`%s` %s', $sortField, $orderBy);
        }

        if (count($sortQuery) > 0) {
            return sprintf('%s ORDER BY %s', $sql, implode(',', $sortQuery));
        }

        return $sql;
    }

    /**
     * @param $field
     * @param $dataSetId
     * @param array $joinConfigs
     * @return null|string
     */
    public function checkFieldInJoinConfig($field, $dataSetId, array $joinConfigs)
    {
        /** @var JoinConfig $joinConfig */
        foreach ($joinConfigs as $joinConfig) {
            $joinFields = $joinConfig->getJoinFields();
            /** @var JoinField $joinField */
            foreach ($joinFields as $joinField) {
                $joinConfigFields = explode(',', $joinField->getField());
                foreach ($joinConfigFields as $index => $joinConfigField) {
                    if ($field == $joinConfigField && $dataSetId == $joinField->getDataSet()) {
                        $outputFields = explode(',', $joinConfig->getOutputField());
                        return isset($outputFields[$index]) ? $outputFields[$index] : null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param QueryBuilder $qb
     * @param $transform
     * @param $newFields
     * @param array $dataSetIndexes
     * @return QueryBuilder
     * @internal param array $metricCalculations
     * @internal param bool $hasGroup
     */
    public function addComparisonPercentTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [])
    {
        if ($transform instanceof ComparisonPercentTransform) {
            $denominator = $transform->getDenominator();
            $tableAlias = null;
            $fieldAndId = $this->getIdSuffixAndField($denominator);

            if ($fieldAndId && array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
            }

            $denominator = $tableAlias === null ? "`$denominator`" : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($denominator));
            $numerator = $transform->getNumerator();
            $fieldAndId = $this->getIdSuffixAndField($numerator);

            if ($fieldAndId && array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
            }

            $numerator = $tableAlias === null ? "`$numerator`" : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($numerator));
            $fieldName = $transform->getFieldName();
            $str = "CASE WHEN $denominator IS NULL THEN NULL WHEN $denominator <= 0 THEN NULL ELSE ABS($numerator - $denominator) / $denominator END AS `$fieldName`";
            $qb->addSelect($str);

            $newFields[] = $fieldName;
        }

        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @param $transform
     * @param $newFields
     * @param array $dataSetIndexes
     * @param bool $removeSuffix
     * @return QueryBuilder
     */
    public function addNewFieldTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [], $removeSuffix = false)
    {
        if ($transform instanceof AddFieldTransform) {
            $fieldName = $transform->getFieldName();
            $conditions = $transform->getConditions();
            $defaultValue = $transform->getValue();
            $whenQueries = [];

            foreach ($conditions as $condition) {
                if (!array_key_exists(AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS, $condition) ||
                    !array_key_exists(AddFieldTransform::FIELD_CONDITIONS_VALUE, $condition)
                ) {
                    continue;
                }

                $expressions = $condition[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS];
                $conditionValue = $condition[AddFieldTransform::FIELD_CONDITIONS_VALUE];

                if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                    $conditionValue = "'$conditionValue'";
                } elseif ($transform->getType() == FieldType::NUMBER) {
                    $conditionValue = intval($conditionValue);
                } elseif ($transform->getType() == FieldType::DECIMAL) {
                    $conditionValue = floatval($conditionValue);
                }

                $when = $this->buildSqlCondition($expressions, $dataSetIndexes, $removeSuffix);

                if (is_string($when)) {
                    $whenQueries[] = "WHEN $when THEN $conditionValue";
                }
            }

            if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                $defaultValue = "'$defaultValue'";
            } elseif ($transform->getType() == FieldType::NUMBER) {
                $defaultValue = intval($defaultValue);
            } elseif ($transform->getType() == FieldType::DECIMAL) {
                $defaultValue = floatval($defaultValue);
            }

            if (count($whenQueries) > 0) {
                $query = implode(' ', $whenQueries);
                $qb->addSelect("CASE $query ELSE $defaultValue END AS `$fieldName`");
            } else {
                $qb->addSelect("$defaultValue AS `$fieldName`");
            }

            $newFields[] = $fieldName;
        }

        return $qb;
    }

    /**
     * @param array $expressions
     * @param array $dataSetIndexes
     * @param bool $removeSuffix
     * @return null|string
     * @throws \Exception
     */
    public function buildSqlCondition(array $expressions, $dataSetIndexes = [], $removeSuffix = false)
    {
        $conditions = [];
        foreach ($expressions as $expression) {
            if (!array_key_exists(AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_CMP, $expression) ||
                !array_key_exists(AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAL, $expression) ||
                !array_key_exists(AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAR, $expression)
            ) {
                continue;
            }

            $conditionComparator = $expression[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_CMP];
            $conditionValue = $expression[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAL];
            $field = $expression[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAR];

            if ($removeSuffix === true) {
                $field = $this->removeIdSuffix($field);
            }

            $tableAlias = null;
            $fieldAndId = $this->getIdSuffixAndField($field);
            if ($fieldAndId && array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
            }

            $field = $tableAlias === null ? $field : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($field));
            $condition = $this->buildSingleSqlCondition($field, $conditionValue, $conditionComparator);

            if ($condition) {
                $conditions[] = $condition;
            }
        }

        if (count($conditions) < 1) {
            return null;
        }

        if (count($conditions) == 1) {
            return $conditions[0];
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @param $var
     * @param $val
     * @param $cmp
     * @param bool $quote
     * @return null|string
     * @throws \Exception
     */
    public function buildSingleSqlCondition($var, $val, $cmp, $quote = true)
    {
        $var = $quote ? "`$var`" : $var;
        switch ($cmp) {
            case NewFieldTransform::IS_INVALID_OPERATOR:
                return "$var IS NULL";
            case NewFieldTransform::SMALLER_OPERATOR:
                return "$var < $val";
            case NewFieldTransform::SMALLER_OR_EQUAL_OPERATOR:
                return "$var <= $val";
                break;
            case NewFieldTransform::GREATER_OPERATOR:
                return "$var > $val";
                break;
            case NewFieldTransform::GREATER_OR_EQUAL_OPERATOR:
                return "$var >= $val";
                break;
            case NewFieldTransform::EQUAL_OPERATOR:
                return "$var = $val";
                break;
            case NewFieldTransform::NOT_EQUAL_OPERATOR:
                return "$var <> $val";
                break;
            case NewFieldTransform::CONTAIN_OPERATOR:
                $conditions = array_map(function($item) use($var) {
                    return "$var LIKE '%$item%'";
                }, $val);
                return join(' OR ', $conditions);
                break;
            case NewFieldTransform::NOT_CONTAIN_OPERATOR:
                $conditions = array_map(function($item) use($var) {
                    return "$var NOT LIKE '%$item%'";
                }, $val);
                return join(' AND ', $conditions);
                break;
            case NewFieldTransform::IN_OPERATOR:
                $val = array_map(function($item) {
                    return "'$item'";
                }, $val);
                $values = implode(',', $val);
                return "$var IN ($values)";
                break;
            case NewFieldTransform::NOT_IN_OPERATOR:
                $val = array_map(function($item) {
                    return "'$item'";
                }, $val);
                $values = implode(',', $val);
                return "$var NOT IN ($values)";
                break;
            case NewFieldTransform::BETWEEN_OPERATOR:
                if (!array_key_exists(NewFieldTransform::START_DATE_KEY, $val) ||
                    !array_key_exists(NewFieldTransform::END_DATE_KEY, $val)
                ) {
                    throw new \Exception('Missing startDate, endDate for Between Expression');
                }
                $startDate = $val[NewFieldTransform::START_DATE_KEY];
                $endDate = $val[NewFieldTransform::END_DATE_KEY];
                return "($var BETWEEN '$startDate' AND '$endDate')";
            default:
                return null;
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param $transform
     * @param $newFields
     * @param array $dataSetIndexes
     * @return QueryBuilder
     * @throws \Exception
     */
    public function addCalculatedFieldTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [])
    {
        if ($transform instanceof AddCalculatedFieldTransform) {
            $fieldName = $transform->getFieldName();
            $expression = $transform->getExpression();
            $expressionForm = $this->normalizeExpression($fieldName, $expression, $dataSetIndexes);
            if ($expressionForm === null) {
                return $qb;
            }
            $expressionForm = "(SELECT $expressionForm)";
            $defaultValues = $transform->getDefaultValue();
            $whenQueries = [];
            foreach ($defaultValues as $defaultValue) {
                if (!array_key_exists(AddCalculatedFieldTransform::DEFAULT_VALUE_KEY, $defaultValue) ||
                    !array_key_exists(AddCalculatedFieldTransform::CONDITION_COMPARATOR_KEY, $defaultValue) ||
                    !array_key_exists(AddCalculatedFieldTransform::CONDITION_FIELD_KEY, $defaultValue) ||
                    !array_key_exists(AddCalculatedFieldTransform::CONDITION_VALUE_KEY, $defaultValue)
                ) {
                    continue;
                }
                $value = $defaultValue[AddCalculatedFieldTransform::DEFAULT_VALUE_KEY];
                if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                    $value = "'$value'";
                } elseif ($transform->getType() == FieldType::NUMBER) {
                    $value = intval($value);
                } elseif ($transform->getType() == FieldType::DECIMAL) {
                    $value = floatval($value);
                }
                $quote = true;
                $field = $defaultValue[AddCalculatedFieldTransform::CONDITION_FIELD_KEY];
                if ($field == NewFieldTransform::CALCULATED_FIELD) {
                    $quote = false;
                    $field = $expressionForm;
                }

                $when = $this->buildSingleSqlCondition(
                    $field,
                    $defaultValue[AddCalculatedFieldTransform::CONDITION_VALUE_KEY],
                    $defaultValue[AddCalculatedFieldTransform::CONDITION_COMPARATOR_KEY],
                    $quote
                );
                if (is_string($when)) {
                    $whenQueries[] = "WHEN $when THEN $value";
                }
            }
            if (count($whenQueries) > 0) {
                $query = implode(' ', $whenQueries);
                $select = "CASE $query ELSE $expressionForm END AS `$fieldName`";
            } else {
                $select = "$expressionForm AS `$fieldName`";
            }
            $qb->addSelect($select);
            $newFields[] = $fieldName;
        }
        return $qb;
    }


    /**
     * @param $fieldName
     * @param $expression
     * @param array $dataSetIndexes
     * @return mixed
     * @throws \Exception
     */
    protected function normalizeExpression($fieldName, $expression, $dataSetIndexes = [])
    {
        if (is_null($expression)) {
            throw new \Exception(sprintf('Expression for calculated field can not be null'));
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fieldsInBracket = $matches[0];
        $fields = $matches[1];
        $newExpressionForm = null;
        $evalExpression = $expression;

        foreach ($fields as $index => $field) {
            $evalExpression = str_replace($fieldsInBracket[$index], strval($index + 1), $evalExpression);
        }

        $language = new ExpressionLanguage();

        try {
            $language->evaluate($evalExpression);
        } catch (SyntaxError $ex) {
            throw new RuntimeException(sprintf('AddCalculatedField : %s', $ex->getMessage()));
        }

        foreach ($fields as $index => $field) {
            if ($fieldsInBracket[$index] == "[$fieldName]") {
                throw new RuntimeException('Can not reference Calculated Field to itself');
            }

            $tableAlias = null;
            $fieldAndId = $this->getIdSuffixAndField($field);
            if ($fieldAndId && array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
            }

            $replaceString = $tableAlias === null ? "`$field`" : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($field));
            $expression = str_replace($fieldsInBracket[$index], $replaceString, $expression);
        }

        return $expression;
    }


    /**
     * @param array $types
     * @param array $searches
     * @param array $joinConfigs
     * @return array
     */
    public function convertSearchToFilter(array $types, array $searches = [], array $joinConfigs = [])
    {
        $filters = [];
        foreach ($searches as $searchField => $searchContent) {
            $alias = $this->convertOutputJoinField($searchField, $joinConfigs);
            if ($alias) {
                $searchField = $alias;
            }

            if (!array_key_exists($searchField, $types)) {
                continue;
            }

            $type = $types[$searchField];

            //Filter number
            if (empty($joinConfigs)) {
                $filter[AbstractFilter::FILTER_FIELD_KEY] = $this->removeIdSuffix($searchField);
            } else {
                $filter[AbstractFilter::FILTER_FIELD_KEY] = $searchField;
            }


            if ($type == FieldType::NUMBER || $type == FieldType::DECIMAL) {
                $conditions = preg_split('/[\s]+/', $searchContent);
                foreach ($conditions as $condition) {
                    $filter[AbstractFilter::FILTER_TYPE_KEY] = FieldType::NUMBER;
                    $result = $this->getMathCondition($condition);
                    if ($result === null) {
                        break;
                        continue;
                    }

                    $filter[AbstractFilter::FILTER_COMPARISON_KEY] = $result[AbstractFilter::FILTER_COMPARISON_KEY];
                    $filter[AbstractFilter::FILTER_COMPARED_VALUE_KEY] = $result[AbstractFilter::FILTER_COMPARED_VALUE_KEY];
                    $filters[] = new NumberFilter($filter);
                }
                continue;
            }

            //Filter text, date...
            if ($type == FieldType::TEXT || $type == FieldType::LARGE_TEXT || $type == FieldType::DATE || $type == FieldType::DATETIME) {
                $filter[AbstractFilter::FILTER_TYPE_KEY] = FieldType::TEXT;
                $filter[AbstractFilter::FILTER_COMPARISON_KEY] = TextFilter::COMPARISON_TYPE_CONTAINS;
                $filter[AbstractFilter::FILTER_COMPARED_VALUE_KEY] = explode(" ", $searchContent);
                $filters[] = new TextFilter($filter);
                continue;
            }
        }

        return $filters;
    }

    /**
     * @param $condition
     * @return array|null
     */
    public function getMathCondition($condition)
    {
        if (preg_match('/([^\d]+)([0-9\.]+)/', $condition, $matches)) {
            $compareOperator = $matches[1];
            $compareValue = (float)$matches[2];

            switch ($compareOperator) {
                case '=':
                case '==':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_EQUAL,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                case '>':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_GREATER,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                case '>=':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                case '<':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_SMALLER,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                case '<=':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                case '!':
                case '!=':
                    return array(
                        AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_NOT_EQUAL,
                        AbstractFilter::FILTER_COMPARED_VALUE_KEY => $compareValue
                    );
                default:
                    return null;
            }
        }

        return null;
    }

    /**
     * bind params values based on given filters
     *
     * @param Statement $stmt
     * @param $filters
     * @param null $dataSetId
     * @return Statement
     */
    private function bindStatementParam(Statement $stmt, $filters, $dataSetId = null)
    {
        $filterKeys = [];
        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if (!array_key_exists($filter->getFieldName(), $filterKeys)) {
                $filterKeys[$filter->getFieldName()] = 1;
            } else {
                $filterKeys[$filter->getFieldName()]++;
            }

            $bindParamName = sprintf('%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0);
            if ($filter instanceof DateFilterInterface) {
                $startDate = $filter->getStartDate();
                if (!$startDate instanceof \DateTime) {
                    $startDate = date_create_from_format('Y-m-d', $startDate);
                }

                if ($startDate instanceof \DateTime) {
                    $startDate = $startDate->format('Y-m-d 00:00:00');
                }

                $endDate = $filter->getEndDate();
                if (!$endDate instanceof \DateTime) {
                    $endDate = date_create_from_format('Y-m-d', $endDate);
                }

                if ($endDate instanceof \DateTime) {
                    $endDate = $endDate->format('Y-m-d 00:00:00');
                }

                $stmt->bindValue(sprintf('startDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0), $startDate, PDO::PARAM_STR);
                $stmt->bindValue(sprintf('endDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0), $endDate, PDO::PARAM_STR);
            } else if ($filter instanceof TextFilterInterface) {
                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_CONTAINS, TextFilter::COMPARISON_TYPE_NOT_CONTAINS, TextFilter::COMPARISON_TYPE_START_WITH,
                    TextFilter::COMPARISON_TYPE_END_WITH, TextFilter::COMPARISON_TYPE_IN, TextFilter::COMPARISON_TYPE_NOT_IN])) {
                    continue;
                }

                $compareValue = $filter->getComparisonValue();
                $stmt->bindValue($bindParamName, $compareValue, PDO::PARAM_STR);
            } else if ($filter instanceof NumberFilterInterface) {
                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_IN, TextFilter::COMPARISON_TYPE_NOT_IN])) {
                    continue;
                }

                $compareValue = $filter->getComparisonValue();
                $stmt->bindValue($bindParamName, $compareValue, PDO::PARAM_INT);
            }
        }

        return $stmt;
    }

    /**
     * @param QueryBuilder $qb
     * @param $filters
     * @param null $dataSetId
     * @return QueryBuilder
     */
    public function bindFilterParam(QueryBuilder $qb, $filters, $dataSetId = null)
    {
        $filterKeys = [];
        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if (!array_key_exists($filter->getFieldName(), $filterKeys)) {
                $filterKeys[$filter->getFieldName()] = 1;
            } else {
                $filterKeys[$filter->getFieldName()]++;
            }

            if ($filter instanceof DateFilterInterface) {
                $startDate = $filter->getStartDate();
                if (!$startDate instanceof \DateTime) {
                    $startDate = date_create_from_format('Y-m-d', $startDate);
                }

                if ($startDate instanceof \DateTime) {
                    $startDate = $startDate->format('Y-m-d 00:00:00');
                }

                $endDate = $filter->getEndDate();
                if (!$endDate instanceof \DateTime) {
                    $endDate = date_create_from_format('Y-m-d', $endDate);
                }

                if ($endDate instanceof \DateTime) {
                    $endDate = $endDate->format('Y-m-d 00:00:00');
                }

                $qb->setParameter(sprintf(':startDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0), $startDate, Type::STRING);
                $qb->setParameter(sprintf(':endDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0), $endDate, Type::STRING);
            } else if ($filter instanceof TextFilterInterface) {
                if (in_array($filter->getComparisonType(), [TextFilter::COMPARISON_TYPE_IN, TextFilter::COMPARISON_TYPE_NOT_IN])) {
                    continue;
                }

                $bindParamName = sprintf(':%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0);
                $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::STRING);
            } else if ($filter instanceof NumberFilterInterface) {
                if (in_array($filter->getComparisonType(), [NumberFilter::COMPARISON_TYPE_IN, NumberFilter::COMPARISON_TYPE_NOT_IN])) {
                    continue;
                }

                $bindParamName = sprintf(':%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0);
                $qb->setParameter($bindParamName, $filter->getComparisonValue(), Type::INTEGER);
            }
        }

        return $qb;
    }

    public function cloneFilter(FilterInterface $filter)
    {
        if ($filter instanceof DateFilter) {
            if ($filter->getDateType() == DateFilter::DATE_TYPE_DYNAMIC) {
                $data = array (
                    DateFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                    DateFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                    DateFilter::DATE_TYPE_FILTER_KEY => $filter->getDateType(),
                    DateFilter::DATE_VALUE_FILTER_KEY => $filter->getDateValue(),
                    DateFilter::DATE_USER_PROVIDED_FILTER_KEY => $filter->isUserDefine()
                );

                return new DateFilter($data);
            }

            $dateValues = $filter->getDateValue();
            foreach ($dateValues as $key => &$value) {
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d');
                }
            }

            unset($value);
            $data = array (
                DateFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                DateFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                DateFilter::DATE_TYPE_FILTER_KEY => $filter->getDateType(),
                DateFilter::DATE_VALUE_FILTER_KEY => $dateValues,
                DateFilter::DATE_USER_PROVIDED_FILTER_KEY => $filter->isUserDefine()
            );

            return new DateFilter($data);
        }

        if ($filter instanceof NumberFilter) {
            $data = array (
                NumberFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                NumberFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                NumberFilter::COMPARISON_TYPE_FILTER_KEY => $filter->getComparisonType(),
                NumberFilter::COMPARISON_VALUE_FILTER_KEY => $filter->getComparisonValue()
            );
            return new NumberFilter($data);
        }

        if ($filter instanceof TextFilter) {
            $data = array (
                TextFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                TextFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                TextFilter::COMPARISON_TYPE_FILTER_KEY => $filter->getComparisonType(),
                TextFilter::COMPARISON_VALUE_FILTER_KEY => $filter->getComparisonValue()
            );
            return new TextFilter($data);
        }

        return $filter;
    }
}