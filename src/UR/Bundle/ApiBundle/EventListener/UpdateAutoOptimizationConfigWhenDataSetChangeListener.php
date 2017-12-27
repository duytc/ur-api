<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Report\SqlBuilder;

class UpdateAutoOptimizationConfigWhenDataSetChangeListener
{
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        // get changes
        if (!$args->hasChangedField(DataSetInterface::DIMENSIONS_COLUMN) && !$args->hasChangedField(DataSetInterface::METRICS_COLUMN)) {
            return;
        }

        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($entity);

        if (!array_key_exists(DataSetInterface::DIMENSIONS_COLUMN, $changedFields) && !array_key_exists(DataSetInterface::METRICS_COLUMN, $changedFields)) {
            return;
        }
        // detect changed metrics, dimensions
        $renameFields = [];
        $actions = $entity->getActions() === null ? [] : $entity->getActions();

        if (array_key_exists('rename', $actions)) {
            $renameFields = $actions['rename'];
        }

        $newDimensions = [];
        $newMetrics = [];
        $updateDimensions = [];
        $updateMetrics = [];
        $deletedMetrics = [];
        $deletedDimensions = [];

        foreach ($changedFields as $field => $values) {
            if ($field === DataSetInterface::DIMENSIONS_COLUMN) {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if ($field === DataSetInterface::METRICS_COLUMN) {
                $this->getChangedFields($values, $renameFields, $newMetrics, $updateMetrics, $deletedMetrics);
            }
        }

        $updateFields = array_merge($updateDimensions, $updateMetrics);
        $deleteFields = array_merge($deletedDimensions, $deletedMetrics);

        /** @var AutoOptimizationConfigDataSetInterface[]|Collection $autoOptimizationConfigDataSets */
        $autoOptimizationConfigDataSets = $entity->getAutoOptimizationConfigDataSets();
        if ($autoOptimizationConfigDataSets instanceof Collection) {
            $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets->toArray();
        }

        foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
            $autoOptimizationConfigDataSet = $this->updateOptimizationConfigDataSet($autoOptimizationConfigDataSet, $updateFields, $deleteFields);
            $em->merge($autoOptimizationConfigDataSet);
            $em->persist($autoOptimizationConfigDataSet);
        }

        /** @var AutoOptimizationConfigInterface[] $autoOptimizationConfigDataSet */
        $autoOptimizationConfigs = array_map(function ($autoOptimizationConfigDataSet) {
            /** @var AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet */
            return $autoOptimizationConfigDataSet->getAutoOptimizationConfig();
        }, $autoOptimizationConfigDataSets);

        //@jennyphuong: Duplicate optimization configs in here

        foreach ($autoOptimizationConfigs as $autoOptimizationConfig) {
            $autoOptimizationConfig = $this->updateOptimizationConfig($autoOptimizationConfig, $entity, $updateFields, $deleteFields);
            $em->merge($autoOptimizationConfig);
            $em->persist($autoOptimizationConfig);
        }
    }

    private function updateOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, array $updateFields, array $deleteFields)
    {
        /*
         * join by
         * [
         *     {
         *        "joinFields":[
         *           {
         *              "dataSet":96,
         *              "field":"date"
         *           },
         *           {
         *              "dataSet":97,
         *              "field":"date2"
         *           }
         *        ],
         *        "outputField":"report_date",
         *        "isVisible":true
         *     }
         * ]
         */
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

        /*
         * transforms
         * [
         *   {
         *     "type": "groupBy",
         *     "fields": [
         *       "date_96",
         *       "tag_96",
         *       "site_96"
         *     ],
         *     "timezone": "UTC",
         *     "aggregationFields": [],
         *     "aggregateAll": true
         *   }
         * ],
         */
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
                        $expressionData [] = '['. $fieldValue .']';
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

        /* filters */
        $filters = $autoOptimizationConfig->getFilters();
        foreach ($filters as &$filter) {
            if (!array_key_exists(AbstractFilter::FILTER_DATA_SET_KEY, $filter)) {
                continue;
            }
            $dataSetId = $filter[AbstractFilter::FILTER_DATA_SET_KEY];
            if ($dataSetId !== $dataSet->getId()) {
                continue;
            }
            if (!array_key_exists(AbstractFilter::FILTER_FIELD_KEY, $filter)) {
                continue;
            }
            $field = $filter[AbstractFilter::FILTER_FIELD_KEY];

            if ($this->deleteFieldValue($field, $deleteFields)) {
                unset($filter);
                continue;
            }

            $field = $this->mappingNewValue($field, $updateFields);
            $filter[AbstractFilter::FILTER_FIELD_KEY] = $field;
        }

        unset($filter);

        $autoOptimizationConfig->setFilters(array_values($filters));

        /*
         * fieldTypes
         * [
         *      <field> => <type>,
         *      ...
         * ]
         */
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

        /*
         * dimensions
         * [
         *      0 => <field>,
         *      ...
         * ]
         */
        $dimensions = $autoOptimizationConfig->getDimensions();
        foreach ($dimensions as $key => $dimension) {
            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($dimension);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($dimensions[$key]);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $dimensions[$key] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        $autoOptimizationConfig->setDimensions(array_values($dimensions));

        /* metrics
         * [
         *      0 => <field>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfig->getMetrics();
        foreach ($metrics as $key => $metric) {
            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($metric);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($metrics[$key]);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);;
            $metrics[$key] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        $autoOptimizationConfig->setMetrics(array_values($metrics));

        /* factors
         * [
         *      <field>
         *      ...
         * ]
         */
        $factors = $autoOptimizationConfig->getFactors();
        foreach ($factors as $key => $field) {
            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($factors[$key]);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $updateField = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $factors[$key] = $updateField;
        }
        $autoOptimizationConfig->setFactors(array_values($factors));

        /* objective
         * [
         *      <field>
         *      ...
         * ]
         */
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
     * @param AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet
     * @param array $updateFields
     * @param array $deleteFields
     * @return AutoOptimizationConfigDataSetInterface
     */
    private function updateOptimizationConfigDataSet(AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet, array $updateFields, array $deleteFields)
    {
        /* filters */
        $filters = $autoOptimizationConfigDataSet->getFilters();
        foreach ($filters as &$filter) {

            if (!array_key_exists(AbstractFilter::FILTER_FIELD_KEY, $filter)) {
                continue;
            }
            $field = $filter[AbstractFilter::FILTER_FIELD_KEY];

            if ($this->deleteFieldValue($field, $deleteFields)) {
                unset($field);
                continue;
            }

            $field = $this->mappingNewValue($field, $updateFields);
            $filter[AbstractFilter::FILTER_FIELD_KEY] = $field;
        }
        unset($filter);
        $autoOptimizationConfigDataSet->setFilters(array_values($filters));
        /*
         * dimensions
         * [
         *      0 = <field>,
         *      ...
         * ]
         */
        $dimensions = $autoOptimizationConfigDataSet->getDimensions();
        foreach ($dimensions as $key => $dimension) {

            if ($this->deleteFieldValue($dimension, $deleteFields)) {
                unset($dimensions[$key]);
                continue;
            }

            $dimensions[$key] = $this->mappingNewValue($dimension, $updateFields);
        }

        $autoOptimizationConfigDataSet->setDimensions(array_values($dimensions));

        /* metrics
         * [
         *      0 = <field>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfigDataSet->getMetrics();
        foreach ($metrics as $key => $metric) {

            if ($this->deleteFieldValue($metric, $deleteFields)) {
                unset($metrics[$key]);
                continue;
            }
            $metrics[$key] = $this->mappingNewValue($metric, $updateFields);
        }

        $autoOptimizationConfigDataSet->setMetrics(array_values($metrics));

        return $autoOptimizationConfigDataSet;
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
     * @param array $values
     * @param array $renameFields
     * @param array $newFields
     * @param array $updateFields
     * @param array $deletedFields
     */
    private function getChangedFields(array $values, array $renameFields, array &$newFields, array &$updateFields, array &$deletedFields)
    {
        $deletedFields = array_diff_assoc($values[0], $values[1]);
        foreach ($renameFields as $renameField) {
            if (!array_key_exists('from', $renameField) || !array_key_exists('to', $renameField)) {
                continue;
            }

            $oldFieldName = $renameField['from'];
            $newFieldName = $renameField['to'];

            if (array_key_exists($oldFieldName, $deletedFields)) {
                $updateFields[$oldFieldName] = $newFieldName;
                unset($deletedFields[$oldFieldName]);
            }
        }

        $newFields = array_diff_assoc($values[1], $values[0]);
        foreach ($updateFields as $updateDimension) {
            if (array_key_exists($updateDimension, $newFields)) {
                unset($newFields[$updateDimension]);
            }
        }
    }

    /**
     * @param $field
     * @return array
     */
    private function getFieldNameAndDataSetId($field)
    {
        $fieldItems = explode('_', $field);
        $dataSetIdFromField = $fieldItems[count($fieldItems)-1];
        $fieldWithoutDataSetId = substr($field, 0, -(strlen($dataSetIdFromField) + 1));

        return [$fieldWithoutDataSetId, $dataSetIdFromField];
    }
}