<?php

namespace UR\Behaviors;


use DateTime;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\OptimizationRule\DataTrainingTableService;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Report\SqlBuilder;

trait OptimizationRuleUtilTrait
{
    public static $OPTIMIZATION_RULE_SCORE_TABLE_NAME_PREFIX_TEMPLATE = '__optimization_rule_score_%d'; // %d is optimization rule id

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return array
     */
    public function getDimensionsMetricsAndTransformField(OptimizationRuleInterface $optimizationRule)
    {
        $reportView = $optimizationRule->getReportView();
        if (!$reportView instanceof ReportViewInterface) {
            return [];
        }

        $fieldNames = $this->getJoinFieldOfDataSets($reportView);

        if(empty($fieldNames)) {
            return $reportView->getFieldTypes();
        }

        $allFields = $reportView->getFieldTypes();
        foreach ($fieldNames as $fieldName) {
            unset($allFields[$fieldName]);
        }

        return $allFields;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    private function getJoinFieldOfDataSets(ReportViewInterface $reportView) {
        $fieldNames = [];

        $jointBys = $reportView->getJoinBy();
        if (empty($jointBys)) {
            return $fieldNames;
        }

        // Remove fields which use to join
        foreach ($jointBys as $jointBy) {
            if (!array_key_exists('joinFields', $jointBy)) {
                continue;
            }
            $joinFields = $jointBy['joinFields'];

            foreach ($joinFields as $joinField) {
                if (!array_key_exists('field', $joinField) || !array_key_exists('dataSet', $joinField)) {
                    continue;
                }
                $fieldNames[] = sprintf('%s_%s', $joinField['field'], $joinField['dataSet']);
            }
        }

        return $fieldNames;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return OptimizationRuleInterface
     */
    public function updateIdentifiersWhenDataSetChange(OptimizationRuleInterface $optimizationRule, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $identifiers = $optimizationRule->getIdentifiers();

        if (empty($identifiers) || !is_array($identifiers)) {
            return $optimizationRule;
        }

        foreach ($identifiers as &$identifier) {
            if (!is_array($identifier) || !array_key_exists(AddField::VALUE_KEY, $identifier)) {
                continue;
            }

            $expression = $identifier[AddField::VALUE_KEY];

            foreach ($updateFields as $oldField => $newField) {
                $expression = str_replace($oldField, $newField, $expression);
            }

            foreach ($deleteFields as $deleteField) {
                $expression = str_replace(sprintf("[%s]", $deleteField), "", $expression);
            }

            $identifier[AddField::VALUE_KEY] = $expression;
        }

        $optimizationRule->setIdentifiers($identifiers);

        return $optimizationRule;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     */
    public function deleteDataTrainingTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getDataTrainingTableName($optimizationRule);

        /** @var DataTrainingTableService $this->dynamicTableService */
        return $this->dynamicTableService->deleteTable($tableName);

    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     */
    public function deleteOptimizationRuleScoreTable(OptimizationRuleInterface $optimizationRule)
    {
        $tableName = $this->getOptimizationRuleScoreTableName($optimizationRule);

        /** @var DataTrainingTableService $this->dynamicTableService */
        return $this->dynamicTableService->deleteTable($tableName);
    }

    /**
     * @param $identifierJsonArray
     * @return mixed
     */
    private function createIdentifierObjectsFromJsonArray($identifierJsonArray)
    {
        if (!is_array($identifierJsonArray) || empty($identifierJsonArray)) {
            return [];
        }

        $allIdentifiers = [];
        foreach ($identifierJsonArray as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            if (!array_key_exists(AddFieldTransform::FIELD_VALUE, $identifier)) {
                continue;
            }

            $identifierObject = new IdentifierGenerator($identifier[AddFieldTransform::FIELD_VALUE]);
            $allIdentifiers[] = $identifierObject;
        }

        return $allIdentifiers;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return OptimizationRuleInterface
     */
    private function updateObjectiveWhenDataSetChange(OptimizationRuleInterface $optimizationRule, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $objective = $optimizationRule->getObjective();
        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($objective);
        if ($dataSetIdFromField == $dataSet->getId()) {

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                $objective = '';
            } else {
                $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                $objective = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            }

            $optimizationRule->setObjective($objective);
        }

        return $optimizationRule;
    }

    /**
     * @param $field
     * @return array
     */
    private function getFieldNameAndDataSetId($field)
    {
        $fieldItems = explode('_', $field);
        $dataSetIdFromField = $fieldItems[count($fieldItems) - 1];
        $fieldWithoutDataSetId = substr($field, 0, -(strlen($dataSetIdFromField) + 1));

        return [$fieldWithoutDataSetId, $dataSetIdFromField];
    }

    private function deleteFieldValue($field, array $deleteFields)
    {
        return (array_key_exists($field, $deleteFields)) ? true : false;
    }

    private function mappingNewValue($field, array $updateFields)
    {
        return (array_key_exists($field, $updateFields)) ? $updateFields[$field] : $field;
    }

    /**
     * @param $fieldsNeedUpdated
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return array
     */
    private function updateFieldsWhenDataSetChange($fieldsNeedUpdated, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        foreach ($fieldsNeedUpdated as $key => $field) {
            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($fieldsNeedUpdated[$key]);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $updateField = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $fieldsNeedUpdated[$key] = $updateField;
        }

        return array_values($fieldsNeedUpdated);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return OptimizationRuleInterface
     */
    private function updateFieldTypeWhenDataSetChange(OptimizationRuleInterface $optimizationRule, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $fieldTypes = $optimizationRule->getFieldTypes();
        $newFieldTypes = [];
        foreach ($fieldTypes as $field => $type) {
            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                $newFieldTypes[$field] = $type;
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newFieldTypes[$field] = $type;
        }

        $optimizationRule->setFieldTypes($newFieldTypes);

        return $optimizationRule;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return OptimizationRuleInterface
     */
    private function updateJoinByWhenDataSetChange(OptimizationRuleInterface $optimizationRule, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        /** @var array $joinBy */
        $joinBy = $optimizationRule->getJoinBy();
        foreach ($joinBy as &$joinBy_) {
            if (!array_key_exists(SqlBuilder::JOIN_CONFIG_JOIN_FIELDS, $joinBy_)) {
                continue;
            }
            $joinFields = $joinBy_[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            foreach ($joinFields as &$joinField) {
                if (!array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $joinField)) {
                    continue;
                }
                $dataSetId = $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
                if ($dataSetId !== $dataSet->getId()) {
                    continue;
                }
                if (!array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $joinField)) {
                    continue;
                }
                $field = $joinField[SqlBuilder::JOIN_CONFIG_FIELD];

                if ($this->deleteFieldValue($field, $deleteFields)) {
                    unset($joinField);
                    continue;
                }

                $field = $this->mappingNewValue($field, $updateFields);
                $joinField[SqlBuilder::JOIN_CONFIG_FIELD] = $field;
            }

            $joinBy_[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] = $joinFields;

            unset($joinField);
        }

        unset($joinBy_);

        $optimizationRule->setJoinBy(array_values($joinBy));

        return $optimizationRule;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return OptimizationRuleInterface
     */
    private function updateTransformsWhenDataSetChange(OptimizationRuleInterface $optimizationRule, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $transforms = $optimizationRule->getTransforms();
        foreach ($transforms as &$transform) {
            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::GROUP_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field);

                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    if ($this->deleteFieldValue($field, $deleteFields)) {
                        unset($field);
                        continue;
                    }

                    $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                    $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field);
                }
            }

            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::ADD_FIELD_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $conditions = [];
                    if (is_array($field)) {
                        if (array_key_exists(AddFieldTransform::FIELD_CONDITIONS, $field)) {
                            $conditions = $field[AddFieldTransform::FIELD_CONDITIONS];
                        }

                        foreach ($conditions as &$condition) {
                            $expressions = $condition[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS];
                            foreach ($expressions as &$expression) {
                                list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($expression[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAR]);
                                if ($dataSetIdFromField != $dataSet->getId()) {
                                    continue;
                                }

                                if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                    unset($expression);
                                    continue;
                                }

                                $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                $fieldNameInExpression = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

                                $expression[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS_VAR] = $fieldNameInExpression;

                                unset($fieldNameInExpression);
                            }
                            $condition[AddFieldTransform::FIELD_CONDITIONS_EXPRESSIONS] = $expressions;
                        }

                        $field[AddFieldTransform::FIELD_CONDITIONS] = $conditions;
                    }
                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                    unset($field, $expression, $expressions, $conditions, $condition);
                }
            }

            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::ADD_CALCULATED_FIELD_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $expressionItems = explode('+', $field[AddCalculatedFieldTransform::EXPRESSION_CALCULATED_FIELD]);
                    $expressionData = [];
                    foreach ($expressionItems as $expressionItem) {
                        $item = substr(trim($expressionItem), 1, -1);
                        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($item);
                        if ($dataSetIdFromField != $dataSet->getId()) {
                            continue;
                        }

                        if ($this->deleteFieldValue(trim($fieldWithoutDataSetId), $deleteFields)) {
                            continue;
                        }

                        $fieldWithoutDataSetId = $this->mappingNewValue(trim($fieldWithoutDataSetId), $updateFields);
                        $fieldValue = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                        $expressionData [] = '[' . $fieldValue . ']';
                    }

                    $expressionData = implode(' + ', $expressionData);
                    $field[AddCalculatedFieldTransform::EXPRESSION_CALCULATED_FIELD] = $expressionData;

                    // check defaultValues
                    if (array_key_exists(AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD, $field)) {
                        $defaultValues = $field[AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD];
                        foreach ($defaultValues as &$defaultValue) {
                            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($defaultValue[AddCalculatedFieldTransform::CONDITION_FIELD_KEY]);
                            if ($dataSetIdFromField != $dataSet->getId()) {
                                continue;
                            }

                            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                unset($defaultValue);
                                continue;
                            }

                            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                            $fieldValue = sprintf('%s_%d', trim($fieldWithoutDataSetId), $dataSetIdFromField);
                            $defaultValue[AddCalculatedFieldTransform::CONDITION_FIELD_KEY] = $fieldValue;
                        }

                        $field[AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD] = $defaultValues;
                    }

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field, $expressionData, $expression, $expressions, $expressionItems, $expressionItem, $fieldValue);
                }
            }

            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::ADD_CONDITION_VALUE_TRANSFORM) {
                continue;
            }

            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::SORT_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $fieldNames = [];
                    if (is_array($field)) {
                        if (array_key_exists('names', $field)) {
                            $fieldNames = $field['names'];
                        }
                    }
                    foreach ($fieldNames as &$fieldName) {
                        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($fieldName);
                        if ($dataSetIdFromField != $dataSet->getId()) {
                            continue;
                        }

                        if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                            unset($fieldName);
                            continue;
                        }

                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                        $fieldName = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                    }
                    $field['names'] = $fieldNames;

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field, $fieldNames);
                }
            }

            if (is_array($transform) && $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::COMPARISON_PERCENT_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    //numerator
                    list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field[ComparisonPercentTransform::NUMERATOR_KEY]);
                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                        $field[ComparisonPercentTransform::NUMERATOR_KEY] = '';
                        continue;
                    } else {
                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                        $field[ComparisonPercentTransform::NUMERATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                    }

                    //denominator
                    list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field[ComparisonPercentTransform::DENOMINATOR_KEY]);
                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                        $field[ComparisonPercentTransform::DENOMINATOR_KEY] = '';
                        continue;
                    } else {
                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                        $field[ComparisonPercentTransform::DENOMINATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                    }

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field);
                }
            }

            unset($transform);
        }

        $optimizationRule->setTransforms(array_values($transforms));

        return $optimizationRule;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return string
     */
    public function getOptimizationRuleScoreTableName(OptimizationRuleInterface $optimizationRule)
    {
        return sprintf(self::$OPTIMIZATION_RULE_SCORE_TABLE_NAME_PREFIX_TEMPLATE, $optimizationRule->getId());
    }
    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @return bool
     */
    public function isOutOfDate(OptimizationIntegrationInterface $optimizationIntegration)
    {
        $frequency = $optimizationIntegration->getOptimizationFrequency();
        $timeStart = $optimizationIntegration->getStartRescoreAt();
        $timeEnd = $optimizationIntegration->getEndRescoreAt();

        if (!$timeStart instanceof DateTime || !$timeEnd instanceof DateTime || empty($frequency)) {
            return true;
        }
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $diff = $now->diff($timeStart);
        $minute = ((int)$diff->format('%a') * 1440) + ((int)$diff->format('%h') * 60) + (int)$diff->format('%i');

        if ($timeEnd < $timeStart) {
            return true;
        }
        switch ($frequency) {
            case DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY:
                return true;
            case DateFilter::DATETIME_DYNAMIC_VALUE_30M:
                if ($minute >= 30) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_1H:
                if ($minute >= 60) {
                    return true;
                }
                break;
            case DateFilter::DATETIME_DYNAMIC_VALUE_24H:
                if ($minute >= 1440) {
                    return true;
                }
                break;
            default:
                break;
        }

        return false;
    }

}