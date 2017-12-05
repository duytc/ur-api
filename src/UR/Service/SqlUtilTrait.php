<?php


namespace UR\Service;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
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
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Exception\RuntimeException;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
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
                $fields = array_map(function ($field) use ($types, $timezone, $removeSuffix) {
                    if (!is_array($field) && array_key_exists($field, $types) && $types[$field] == FieldType::DATETIME) {
                        if ($removeSuffix === true) {
                            $field = $this->removeIdSuffix($field);
                        }

                        if ($timezone != 'UTC') {
                            return sprintf("DATE(CONVERT_TZ(`$field`, 'UTC', '$timezone'))");
                        }

                        return sprintf("DATE(`$field`)");
                    }

                    if (!is_array($field)) {
                        $field = $removeSuffix === true ? $this->removeIdSuffix($field) : $field;
                        return "`$field`";
                    }
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

    /**
     * @param $field
     * @param array $joinConfigs
     * @param array $dataSetIndexes
     * @return null|string
     */
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
                            return sprintf('`t%d`.`%s`', $dataSetIndexes[$joinField->getDataSet()], $inputJoinFields[$index]);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $field
     * @param array $joinConfigs
     * @return null|string
     */
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
        if (!empty($sortField)) {
            if (!in_array(strtolower($orderBy), ['asc', 'desc'])) {
                $orderBy = 'asc';
            }

            $sortField = str_replace('"', '', $sortField);
            $qb->addOrderBy("`$sortField`", $orderBy);
        }

        if (!is_array($transforms)) {
            return $qb;
        }

        foreach ($transforms as $transform) {
            if ($transform instanceof SortByTransform) {
                $sortObjects = $transform->getSortObjects();
                foreach ($sortObjects as $sortObject) {
                    $names = $sortObject[SortByTransform::FIELDS_KEY];
                    if (!empty($names)) {
                        foreach ($names as $name) {
                            if ($name == $sortField) {
                                continue;
                            }
                            $qb->addOrderBy("`$name`", $sortObject[SortByTransform::SORT_DIRECTION_KEY]);
                        }
                    }
                }
            }
        }

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
     * @param bool $removeSuffix
     * @return QueryBuilder
     */
    public function addComparisonPercentTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [], $removeSuffix = true)
    {
        if ($transform instanceof ComparisonPercentTransform) {
            $denominator = $transform->getDenominator();
            $numerator = $transform->getNumerator();

            if ($removeSuffix) {
                $tableAlias = null;
                $fieldAndId = $this->getIdSuffixAndField($denominator);

                if ($fieldAndId) {
                    $denominator = $fieldAndId['field'];
                    if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                        $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                    }
                }

                $denominator = $tableAlias === null ? "`$denominator`" : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($denominator));

                $fieldAndId = $this->getIdSuffixAndField($numerator);

                if ($fieldAndId) {
                    $numerator = $fieldAndId['field'];
                    if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                        $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                    }
                }

                $numerator = $tableAlias === null ? "`$numerator`" : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($numerator));
            }

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
     * @param array $joinConfig
     * @return QueryBuilder
     */
    public function addNewFieldTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [], array $joinConfig = [])
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
                    $conditionValue = intval(round($conditionValue, 0));
                } elseif ($transform->getType() == FieldType::DECIMAL) {
                    $conditionValue = floatval($conditionValue);
                }

                $when = $this->buildSqlCondition($transform->getIsPostGroup(), $expressions, $dataSetIndexes, $joinConfig);

                if (is_string($when)) {
                    $whenQueries[] = "WHEN $when THEN $conditionValue";
                }
            }

            if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                $defaultValue = "'$defaultValue'";
            } elseif ($transform->getType() == FieldType::NUMBER) {
                $defaultValue = intval(round($defaultValue, 0));
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
     * @param $postGroup
     * @param array $expressions
     * @param array $dataSetIndexes
     * @param array $joinConfig
     * @return null|string
     * @throws \Exception
     */
    public function buildSqlCondition($postGroup, array $expressions, $dataSetIndexes = [], array $joinConfig = [])
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

            $quote = true;
            if (!$postGroup) {
                $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfig, $dataSetIndexes);
                if ($alias) {
                    $field = $alias;
                    $quote = false;
                } else {
                    $tableAlias = null;
                    $fieldAndId = $this->getIdSuffixAndField($field);
                    if ($fieldAndId) {
                        $field = $fieldAndId['field'];
                        if (empty($dataSetIndexes)) {
                            $field = "t.$field";
                        } elseif (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                            $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                        }
                        $quote = false;
                    }

                    $field = $tableAlias === null ? $field : sprintf('%s.%s', $tableAlias, $this->removeIdSuffix($field));
                }
            }

            $condition = $this->buildSingleSqlCondition($field, $conditionValue, $conditionComparator, $quote);

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

            case NewFieldTransform::GREATER_OPERATOR:
                return "$var > $val";

            case NewFieldTransform::GREATER_OR_EQUAL_OPERATOR:
                return "$var >= $val";

            case NewFieldTransform::EQUAL_OPERATOR:
                return "$var = '$val'"; // single quote for both number and text

            case NewFieldTransform::NOT_EQUAL_OPERATOR:
                return "$var <> '$val'"; // single quote for both number and text

            case NewFieldTransform::CONTAIN_OPERATOR:
                $conditions = array_map(function ($item) use ($var) {
                    return "$var LIKE '%$item%'";
                }, $val);

                return join(' OR ', $conditions);

            case NewFieldTransform::NOT_CONTAIN_OPERATOR:
                $conditions = array_map(function ($item) use ($var) {
                    return "$var NOT LIKE '%$item%'";
                }, $val);

                return join(' AND ', $conditions);

            case NewFieldTransform::IN_OPERATOR:
                $val = array_map(function ($item) {
                    return "'$item'";
                }, $val);

                $values = implode(',', $val);
                return "$var IN ($values)";

            case NewFieldTransform::NOT_IN_OPERATOR:
                $val = array_map(function ($item) {
                    return "'$item'";
                }, $val);

                $values = implode(',', $val);
                return "$var NOT IN ($values)";

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
     * @param array $joinConfig
     * @param bool $removeSuffix
     * @return QueryBuilder
     * @throws PublicSimpleException
     * @throws \Exception
     */
    public function addCalculatedFieldTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [], array $joinConfig = [], $removeSuffix = false)
    {
        if ($transform instanceof AddCalculatedFieldTransform) {
            $fieldName = $transform->getFieldName();
            $expression = $transform->getExpression();

            $expressionForm = $this->normalizeExpression(AddCalculatedFieldTransform::TRANSFORMS_TYPE, $fieldName, $expression, $newFields, $dataSetIndexes, $joinConfig, $removeSuffix, $transform->isConvertEmptyValueToZero());

            if ($expressionForm === null) return $qb;

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
                    $value = intval(round($value, 0));
                } elseif ($transform->getType() == FieldType::DECIMAL) {
                    $value = floatval($value);
                }

                $quote = true;
                $field = $defaultValue[AddCalculatedFieldTransform::CONDITION_FIELD_KEY];
                if ($field == NewFieldTransform::CALCULATED_FIELD) {
                    $quote = false;
                    $field = $expressionForm;
                } else {
                    if (!$transform->getIsPostGroup()) {
                        $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfig, $dataSetIndexes);
                        if ($alias) {
                            $field = $alias;
                            $quote = false;
                        } else {
                            $tableAlias = null;
                            $fieldAndId = $this->getIdSuffixAndField($field);
                            if ($fieldAndId) {
                                $field = $fieldAndId['field'];
                                if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                                    $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                                }
                            }

                            $field = $tableAlias !== null ? "`$tableAlias`.`$field`" : "`t`.`$field`";
                            $quote = false;
                        }
                    }
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
     * @param QueryBuilder $qb
     * @param $transform
     * @param $newFields
     * @param array $dataSetIndexes
     * @param array $joinConfig
     * @param bool $removeSuffix
     * @return QueryBuilder
     * @throws PublicSimpleException
     * @throws \Exception
     */
    public function addConditionValueTransformQuery(QueryBuilder $qb, $transform, &$newFields, $dataSetIndexes = [], array $joinConfig = [], $removeSuffix = false)
    {
        if ($transform instanceof AddConditionValueTransform) {
            $fieldName = $transform->getFieldName();

            $defaultValue = $transform->getDefaultValue();

            // cast type for $defaultValue
            $defaultValue = $this->reformatData($defaultValue, $transform->getType());
            if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                $defaultValue = (null !== $defaultValue) ? "'$defaultValue'" : "NULL";
            } elseif ($transform->getType() == FieldType::NUMBER) {
                $defaultValue = (null !== $defaultValue) ? intval(round($defaultValue, 0)) : "NULL";
            } elseif ($transform->getType() == FieldType::DECIMAL) {
                $defaultValue = (null !== $defaultValue) ? floatval($defaultValue) : "NULL";
            }

            $mappedValues = $transform->getMappedValues();

            $whenQueries = [];

            foreach ($mappedValues as $addConditionValueConfig) {
                if (!$addConditionValueConfig instanceof ReportViewAddConditionalTransformValueInterface) {
                    continue;
                }

                $addConditionValueDefaultValue = $addConditionValueConfig->getDefaultValue();

                // cast type for $addConditionValueDefaultValue
                $addConditionValueDefaultValue = $this->reformatData($addConditionValueDefaultValue, $transform->getType());
                if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                    $addConditionValueDefaultValue = (null !== $addConditionValueDefaultValue) ? "'$addConditionValueDefaultValue'" : "NULL";
                } elseif ($transform->getType() == FieldType::NUMBER) {
                    $addConditionValueDefaultValue = (null !== $addConditionValueDefaultValue) ? intval(round($addConditionValueDefaultValue, 0)) : "NULL";
                } elseif ($transform->getType() == FieldType::DECIMAL) {
                    $addConditionValueDefaultValue = (null !== $addConditionValueDefaultValue) ? floatval($addConditionValueDefaultValue) : "NULL";
                }

                /*
                 * notice - the expected result is:
                 * sharedConditions returns true (so, if failed => try next addConditionValueConfig)
                 * a condition in conditions returns true (so, if failed => try next condition, if no condition matched => return default value)
                 *
                 * SELECT
                 *     ...,
                 *     CASE
                 *         WHEN <sharedCondition-expression 1> && <sharedCondition-expression 2> THEN
                 *              CASE
                 *                  WHEN <condition 1-expression 1> && <condition 1-expression 2> THEN <condition 1-value>
                 *                  WHEN <condition 2-expression 1> && <condition 2-expression 2> THEN <condition 2-value>
                 *              ELSE <sharedCondition 1-defaultValue>
                 *              END
                 *         ELSE <defaultValue>
                 *     END AS ...,
                 *     ...
                 */

                // calculate shared conditions
                $sharedConditions = $addConditionValueConfig->getSharedConditions();
                $whenSharedConditionsExpressionsQueries = [];

                foreach ($sharedConditions as $sharedConditionExpression) {
                    $quote = true;
                    $field = $sharedConditionExpression[AddConditionValueTransform::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD];
                    if (!$transform->getIsPostGroup()) {
                        $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfig, $dataSetIndexes);
                        if ($alias) {
                            $field = $alias;
                            $quote = false;
                        } else {
                            $tableAlias = null;
                            $fieldAndId = $this->getIdSuffixAndField($field);
                            if ($fieldAndId) {
                                $field = $fieldAndId['field'];
                                if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                                    $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                                }
                            }

                            $field = $tableAlias !== null ? "`$tableAlias`.`$field`" : "`t`.`$field`";
                            $quote = false;
                        }
                    }

                    $whenSharedExpressionExpression = $this->buildSingleSqlCondition(
                        $field,
                        $sharedConditionExpression[AddConditionValueTransform::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE],
                        $sharedConditionExpression[AddConditionValueTransform::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR],
                        $quote
                    );

                    if (is_string($whenSharedExpressionExpression)) {
                        $whenSharedConditionsExpressionsQueries[] = $whenSharedExpressionExpression;
                    }
                }

                $whenSharedConditionsQuery = (!empty($whenSharedConditionsExpressionsQueries))
                    ? "WHEN " . implode(' AND ', $whenSharedConditionsExpressionsQueries)
                    : "WHEN 1 ";

                // continue calculate conditions after sharedConditions passed
                $conditions = $addConditionValueConfig->getConditions();
                $whenConditionsQueries = [];

                foreach ($conditions as $condition) {
                    $conditionValue = $condition[AddConditionValueTransform::VALUES_KEY_CONDITIONS_VALUE];

                    $conditionValue = $this->reformatData($conditionValue, $transform->getType());
                    if (in_array($transform->getType(), [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATETIME, FieldType::DATE])) {
                        $conditionValue = (null !== $conditionValue) ? "'$conditionValue'" : "NULL";
                    } elseif ($transform->getType() == FieldType::NUMBER) {
                        $conditionValue = (null !== $conditionValue) ? intval(round($conditionValue, 0)) : "NULL";
                    } elseif ($transform->getType() == FieldType::DECIMAL) {
                        $conditionValue = (null !== $conditionValue) ? floatval($conditionValue) : "NULL";
                    }

                    $conditionExpressions = $condition[AddConditionValueTransform::VALUES_KEY_CONDITIONS_EXPRESSIONS];
                    if (!is_array($conditionExpressions)) {
                        continue;
                    }

                    $whenConditionsExpressionsQueries = [];

                    foreach ($conditionExpressions as $conditionExpression) {
                        $quote = true;
                        $field = $conditionExpression[AddConditionValueTransform::VALUES_KEY_CONDITIONS_EXPRESSIONS_FIELD];
                        if (!$transform->getIsPostGroup()) {
                            $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfig, $dataSetIndexes);
                            if ($alias) {
                                $field = $alias;
                                $quote = false;
                            } else {
                                $tableAlias = null;
                                $fieldAndId = $this->getIdSuffixAndField($field);
                                if ($fieldAndId) {
                                    $field = $fieldAndId['field'];
                                    if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                                        $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                                    }
                                }

                                $field = $tableAlias !== null ? "`$tableAlias`.`$field`" : "`t`.`$field`";
                                $quote = false;
                            }
                        }

                        $whenConditionExpression = $this->buildSingleSqlCondition(
                            $field,
                            $conditionExpression[AddConditionValueTransform::VALUES_KEY_CONDITIONS_EXPRESSIONS_VALUE],
                            $conditionExpression[AddConditionValueTransform::VALUES_KEY_CONDITIONS_EXPRESSIONS_COMPARATOR],
                            $quote
                        );

                        if (is_string($whenConditionExpression)) {
                            $whenConditionsExpressionsQueries[] = $whenConditionExpression;
                        }
                    }

                    $whenConditionsQueries[] = (!empty($whenConditionsExpressionsQueries))
                        ? "WHEN " . implode(' AND ', $whenConditionsExpressionsQueries) . " THEN $conditionValue"
                        : "WHEN 0 THEN $conditionValue";
                }


                // combine sharedConditions and conditions sql
                $whenQueries[] = (empty($whenConditionsQueries))
                    ? (
                        $whenSharedConditionsQuery . " THEN " . $addConditionValueDefaultValue
                    )
                    : (
                        $whenSharedConditionsQuery . " THEN "
                        . " CASE "
                        . implode(' ', $whenConditionsQueries)
                        . " ELSE $addConditionValueDefaultValue"
                        . " END"
                    );
            }

            if (count($whenQueries) > 0) {
                $query = implode(' ', $whenQueries);
                $select = "CASE $query ELSE $defaultValue END AS `$fieldName`";
            } else {
                $select = "CASE WHEN 1 THEN $defaultValue END AS `$fieldName`";
            }

            $qb->addSelect($select);
            $newFields[] = $fieldName;
        }

        return $qb;
    }

    /**
     * @param $transformType
     * @param $fieldName
     * @param $expression
     * @param array $newFields
     * @param array $dataSetIndexes
     * @param array $joinConfig
     * @param bool $removeSuffix
     * @param bool $isConvertEmptyValueToZero
     * @return mixed
     * @throws PublicSimpleException
     * @throws \Exception
     */
    protected function normalizeExpression($transformType, $fieldName, $expression, array $newFields, $dataSetIndexes = [], array $joinConfig = [], $removeSuffix = true, $isConvertEmptyValueToZero = false)
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
        } catch (\Exception $ex) {
            throw new PublicSimpleException(sprintf('Warning: Wrong expression of %s', $transformType));
        }

        foreach ($fields as $index => $field) {
            if ($fieldsInBracket[$index] == "[$fieldName]") {
                throw new RuntimeException('Can not reference Calculated Field to itself');
            }

            if ($removeSuffix === false) {
                $field = "`$field`";
            } else {
                $alias = $this->convertOutputJoinFieldToAlias($field, $joinConfig, $dataSetIndexes);
                if ($alias) {
                    $field = $alias;
                } else {
                    $tableAlias = null;
                    $fieldAndId = $this->getIdSuffixAndField($field);
                    if ($fieldAndId) {
                        $field = $fieldAndId['field'];
                        if (array_key_exists($fieldAndId['id'], $dataSetIndexes)) {
                            $tableAlias = sprintf('t%d', $dataSetIndexes[$fieldAndId['id']]);
                        }
                    }

                    //$field = $tableAlias !== null ? "`$tableAlias`.`$field`" : (empty($joinConfig) ? "`t`.`$field`" : "`$field`");
                    $field = ($tableAlias !== null)
                        ? "`$tableAlias`.`$field`"
                        : (
                        empty($joinConfig) && !in_array($field, $newFields)
                            ? "`t`.`$field`"
                            : "`$field`"
                        );
                }
            }

            /*
             * wrap IFNULL if isConvertEmptyValueToZero
             * e.g: 1+2+null = null, but IFNULL(1,0)+IFNULL(2,0)+IFNULL(null,0) = 3
             *
             * now, we do convert to support IFNULL
             * e.g: 1+2+null => IFNULL(1,0)+IFNULL(2,0)+IFNULL(null,0)
             */
            if ($isConvertEmptyValueToZero) {
                $field = sprintf('IFNULL(%s, 0)', $field);
            }

            $expression = str_replace($fieldsInBracket[$index], $field, $expression);
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
            }
        }

        if (is_numeric($condition)) {
            return array(
                AbstractFilter::FILTER_COMPARISON_KEY => NumberFilter::COMPARISON_TYPE_EQUAL,
                AbstractFilter::FILTER_COMPARED_VALUE_KEY => floatval($condition)
            );
        }

        return null;
    }

    /**
     * @param FilterInterface $filter
     * @return FilterInterface
     */
    public function cloneFilter(FilterInterface $filter)
    {
        if ($filter instanceof DateFilter) {
            if ($filter->getDateType() == DateFilter::DATE_TYPE_DYNAMIC) {
                $data = array(
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
            $data = array(
                DateFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                DateFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                DateFilter::DATE_TYPE_FILTER_KEY => $filter->getDateType(),
                DateFilter::DATE_VALUE_FILTER_KEY => $dateValues,
                DateFilter::DATE_USER_PROVIDED_FILTER_KEY => $filter->isUserDefine()
            );

            return new DateFilter($data);
        }

        if ($filter instanceof NumberFilter) {
            $data = array(
                NumberFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                NumberFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                NumberFilter::COMPARISON_TYPE_FILTER_KEY => $filter->getComparisonType(),
                NumberFilter::COMPARISON_VALUE_FILTER_KEY => $filter->getComparisonValue()
            );
            return new NumberFilter($data);
        }

        if ($filter instanceof TextFilter) {
            $data = array(
                TextFilter::FILED_NAME_FILTER_KEY => $filter->getFieldName(),
                TextFilter::FIELD_TYPE_FILTER_KEY => $filter->getFieldType(),
                TextFilter::COMPARISON_TYPE_FILTER_KEY => $filter->getComparisonType(),
                TextFilter::COMPARISON_VALUE_FILTER_KEY => $filter->getComparisonValue()
            );
            return new TextFilter($data);
        }

        return $filter;
    }

    /**
     * @param ParamsInterface $params
     * @return array
     */
    public function createMagicMaps(ParamsInterface $params)
    {
        $dataSets = is_array($params->getDataSets()) ? $params->getDataSets() : [];
        $allDimensionMetrics = [];
        foreach ($dataSets as $index => $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $dataSetId = $dataSet->getDataSetId();
            $dimensions = is_array($dataSet->getDimensions()) ? $dataSet->getDimensions() : [];
            $metrics = is_array($dataSet->getMetrics()) ? $dataSet->getMetrics() : [];

            $localDimensionMetrics = array_merge($dimensions, $metrics);

            foreach ($localDimensionMetrics as $field) {
                $allDimensionMetrics[sprintf("%s_%s", $field, $dataSetId)] = sprintf("t%s.`%s`", count($dataSets) > 1 ? $index : "", $field);
            }
        }

        return $allDimensionMetrics;
    }
}