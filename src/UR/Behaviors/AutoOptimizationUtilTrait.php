<?php

namespace UR\Behaviors;


use Doctrine\Common\Collections\Collection;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\AutoOptimization\DTO\IdentifierGenerator;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Report\SqlBuilder;

trait AutoOptimizationUtilTrait
{
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

    private function mappingNewValue($field, array $updateFields)
    {
        return (array_key_exists($field, $updateFields)) ? $updateFields[$field] : $field;
    }

    private function deleteFieldValue($field, array $deleteFields)
    {
        return (array_key_exists($field, $deleteFields)) ? true : false;
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

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return AutoOptimizationConfigInterface
     */
    private function updateObjectiveWhenDataSetChange(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $objective = $autoOptimizationConfig->getObjective();
        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($objective);
        if ($dataSetIdFromField == $dataSet->getId()) {

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                $objective = '';
            } else {
                $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                $objective = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            }

            $autoOptimizationConfig->setObjective($objective);
        }

        return $autoOptimizationConfig;
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
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return AutoOptimizationConfigInterface
     */
    private function updateFieldTypeWhenDataSetChange(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $fieldTypes = $autoOptimizationConfig->getFieldTypes();
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

        $autoOptimizationConfig->setFieldTypes($newFieldTypes);

        return $autoOptimizationConfig;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return AutoOptimizationConfigInterface
     */
    private function updateJoinByWhenDataSetChange(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        /** @var array $joinBy */
        $joinBy = $autoOptimizationConfig->getJoinBy();
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

        $autoOptimizationConfig->setJoinBy(array_values($joinBy));

        return $autoOptimizationConfig;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return AutoOptimizationConfigInterface
     */
    private function updateTransformsWhenDataSetChange(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $transforms = $autoOptimizationConfig->getTransforms();
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

        $autoOptimizationConfig->setTransforms(array_values($transforms));

        return $autoOptimizationConfig;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return array
     */
    public function getDimensionsMetricsAndTransformField(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        /** @var AutoOptimizationConfigDataSetInterface[]|Collection $autoOptimizationConfigDataSets */
        $autoOptimizationConfigDataSets = $autoOptimizationConfig->getAutoOptimizationConfigDataSets();
        $dimensionsAndMetricsSelected = [];

        $dimensionsAndMetrics = $autoOptimizationConfig->getFieldTypes();
        //$dimensions from autoOptimizationConfig
        foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
            $dimensions = $autoOptimizationConfigDataSet->getDimensions();
            $metrics = $autoOptimizationConfigDataSet->getMetrics();
            $dimensionsAndMetricsSelectedDataSet = array_merge($dimensions, $metrics);
            foreach ($dimensionsAndMetricsSelectedDataSet as &$item) {
                $item = $item . '_' . $autoOptimizationConfigDataSet->getDataSet()->getId();
            }
            $dimensionsAndMetricsSelected = array_merge($dimensionsAndMetricsSelected, $dimensionsAndMetricsSelectedDataSet);
            unset($dimensions, $metrics, $dimensionsAndMetricsSelectedDataSet);
        }

        // joinBy
        $joinBy = $autoOptimizationConfig->getJoinBy();
        if (is_array($joinBy) && !empty($joinBy)) {
            foreach ($joinBy as &$joinBy_) {
                if (!array_key_exists(SqlBuilder::JOIN_CONFIG_JOIN_FIELDS, $joinBy_)) {
                    continue;
                }
                $joinFields = $joinBy_[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
                foreach ($joinFields as &$joinField) {
                    if (!array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $joinField)) {
                        continue;
                    }

                    if (!array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $joinField)) {
                        continue;
                    }
                    $field = $joinField[SqlBuilder::JOIN_CONFIG_FIELD] . '_' . $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
                    $dimensionsAndMetricsSelected = array_values(array_diff($dimensionsAndMetricsSelected, array($field)));
                }

                if ($joinBy_[SqlBuilder::JOIN_CONFIG_VISIBLE] == false) {
                    continue;
                }

                if (preg_match('/\s/', $joinBy_[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD])) {
                    $fieldNameOutPutJoin = str_replace(' ', '_', $joinBy_[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD]);
                    $dimensionsAndMetricsSelected = array_merge(array($fieldNameOutPutJoin), $dimensionsAndMetricsSelected);
                } else {
                    $dimensionsAndMetricsSelected = array_merge(array($joinBy_[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD]), $dimensionsAndMetricsSelected);
                }

            }
            unset($joinBy, $joinField, $field);
        }

        foreach ($dimensionsAndMetrics as $fieldName => $fieldType) {

            if (preg_match('/\s/', $fieldName)) {
                $newFieldName = str_replace(' ', '_', $fieldName);
                if (in_array($newFieldName, $dimensionsAndMetricsSelected)) {
                    unset($dimensionsAndMetrics[$fieldName]);
                    $dimensionsAndMetrics[$newFieldName] = $fieldType;

                    continue;
                }
            }

            if (!in_array($fieldName, $dimensionsAndMetricsSelected)) {
                unset($dimensionsAndMetrics[$fieldName]);
            }
        }

        // $dimensions from autoOptimizationConfig transforms
        $fieldNameFromTransform = [];
        foreach ($autoOptimizationConfig->getTransforms() as $transform) {
            if (!is_array($transform)) {
                continue;
            }
            if ($transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::GROUP_TRANSFORM) {
                continue;
            }
            $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
            foreach ($fields as $field) {
                if (isset($field['field']) && $field['type']) {
                    $fieldNameTransform = str_replace(' ', '_', $field['field']);
                    $dimensionsAndMetrics[$fieldNameTransform] = $field['type'];
                }
            }
        }

        unset($metrics, $dimensionsAndMetricsSelected, $fieldWithoutDataSetId, $fieldNameFromTransform);

        return $dimensionsAndMetrics ? $dimensionsAndMetrics : [];
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param DataSetInterface $dataSet
     * @param $updateFields
     * @param $deleteFields
     * @return AutoOptimizationConfigInterface
     */
    public function updateIdentifiersWhenDataSetChange(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, $updateFields, $deleteFields)
    {
        $identifiers = $autoOptimizationConfig->getIdentifiers();

        if (empty($identifiers) || !is_array($identifiers)) {
            return $autoOptimizationConfig;
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

        $autoOptimizationConfig->setIdentifiers($identifiers);

        return $autoOptimizationConfig;
    }
}