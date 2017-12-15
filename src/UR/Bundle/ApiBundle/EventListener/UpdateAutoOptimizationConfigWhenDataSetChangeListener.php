<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Report\SqlBuilder;

class UpdateAutoOptimizationConfigWhenDataSetChangeListener
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';
    const EXPRESSIONS_KEY = 'expressions';
    const EXPRESSION_KEY = 'expression';
    const CONDITIONS_KEY = 'conditions';
    const CONDITION_FIELD_KEY = 'conditionField';
    const NAMES_KEY = 'names';
    const DENOMINATOR_KEY = 'denominator';
    const NUMERATOR_KEY = 'numerator';
    const VAR_KEY = 'var';
    const DEFAULT_VALUES_KEY = 'defaultValues';

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        // get changes
        if (!$args->hasChangedField(self::DIMENSIONS_KEY) && !$args->hasChangedField(self::METRICS_KEY)) {
            return;
        }

        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($entity);

        if (!array_key_exists('dimensions', $changedFields) && !array_key_exists('metrics', $changedFields)) {
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
            if ($field === 'dimensions') {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if ($field === 'metrics') {
                $this->getChangedFields($values, $renameFields, $newMetrics, $updateMetrics, $deletedMetrics);
            }
        }

        $newFields = array_merge($newDimensions, $newMetrics);
        $updateFields = array_merge($updateDimensions, $updateMetrics);
        $deleteFields = array_merge($deletedDimensions, $deletedMetrics);

        /** @var AutoOptimizationConfigDataSetInterface[]|Collection $autoOptimizationConfigDataSets */
        $autoOptimizationConfigDataSets = $entity->getAutoOptimizationConfigDataSets();
        if ($autoOptimizationConfigDataSets instanceof Collection) {
            $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets->toArray();
        }

        /** @var AutoOptimizationConfigInterface[] $autoOptimizationConfigDataSet */
        $autoOptimizationConfigs = array_map(function ($autoOptimizationConfigDataSet) {
            /** @var AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet */
            return $autoOptimizationConfigDataSet->getAutoOptimizationConfig();
        }, $autoOptimizationConfigDataSets);

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

        $autoOptimizationConfig->setJoinBy($joinBy);

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
            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::GROUP_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field);
                    $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field);

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

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::ADD_FIELD_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $conditions = [];
                    if (is_array($field)) {
                        if (array_key_exists(self::CONDITIONS_KEY, $field)) {
                            $conditions = $field[self::CONDITIONS_KEY];
                        }

                        foreach ($conditions as &$condition) {
                            $expressions = $condition[self::EXPRESSIONS_KEY];
                            foreach ($expressions as &$expression) {
                                $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $expression[self::VAR_KEY]);
                                $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $expression[self::VAR_KEY]);

                                if ($dataSetIdFromField != $dataSet->getId()) {
                                    continue;
                                }

                                if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                    unset($expression);
                                    continue;
                                }

                                $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                $fieldNameInExpression = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

                                $expression[self::VAR_KEY] = $fieldNameInExpression;

                                unset($fieldNameInExpression);
                            }
                            $condition[self::EXPRESSIONS_KEY] = $expressions;
                        }

                        $field[self::CONDITIONS_KEY] = $conditions;
                    }
                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                    unset($field, $expression, $expressions, $conditions, $condition);
                }
            }

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::ADD_CALCULATED_FIELD_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $expressionItems = explode('+', $field[self::EXPRESSION_KEY]);
                    $expressionData = [];
                    foreach ($expressionItems as $expressionItem) {
                        $item = substr(trim($expressionItem), 1, -1);
                        $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $item);
                        $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $item);
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
                    $field[self::EXPRESSION_KEY] = $expressionData;

                    // check defaultValues
                    if (array_key_exists(self::DEFAULT_VALUES_KEY, $field)) {
                        $defaultValues = $field[self::DEFAULT_VALUES_KEY];
                        foreach ($defaultValues as &$defaultValue) {
                            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $defaultValue[self::CONDITION_FIELD_KEY]);
                            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $defaultValue[self::CONDITION_FIELD_KEY]);
                            if ($dataSetIdFromField != $dataSet->getId()) {
                                continue;
                            }

                            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                unset($defaultValue);
                                continue;
                            }

                            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                            $fieldValue = sprintf('%s_%d', trim($fieldWithoutDataSetId), $dataSetIdFromField);
                            $defaultValue[self::CONDITION_FIELD_KEY] = $fieldValue;
                        }

                        $field[self::DEFAULT_VALUES_KEY] = $defaultValues;
                    }

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field, $expressionData, $expression, $expressions, $expressionItems, $expressionItem, $fieldValue);
                }
            }

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::ADD_CONDITION_VALUE_TRANSFORM) {
                continue;
            }

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::SORT_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    $fieldNames = [];
                    if (is_array($field)) {
                        if (array_key_exists(self::NAMES_KEY, $field)) {
                            $fieldNames = $field[self::NAMES_KEY];
                        }
                    }
                    foreach ($fieldNames as &$fieldName) {
                        $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $fieldName);
                        $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $fieldName);
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
                    $field[self::NAMES_KEY] = $fieldNames;

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field, $fieldNames);
                }
            }

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::COMPARISON_PERCENT_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    //numerator
                    $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field[self::NUMERATOR_KEY]);
                    $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field[self::NUMERATOR_KEY]);
                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                        $field[self::NUMERATOR_KEY] = '';
                        continue;
                    } else {
                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                        $field[self::NUMERATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                    }

                    //denominator
                    $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field[self::DENOMINATOR_KEY]);
                    $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field[self::DENOMINATOR_KEY]);
                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                        $field[self::DENOMINATOR_KEY] = '';
                        continue;
                    } else {
                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                        $field[self::DENOMINATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                    }

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field);
                }
            }

            unset($transform);
        }

        $autoOptimizationConfig->setTransforms($transforms);

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

        $autoOptimizationConfig->setFilters($filters);

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
            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field);
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
        foreach ($dimensions as &$dimension) {
            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $dimension);
            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $dimension);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($dimension);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $dimension = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        $autoOptimizationConfig->setDimensions($dimensions);

        /* metrics
         * [
         *      0 => <field>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfig->getMetrics();
        foreach ($metrics as &$metric) {
            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $metric);
            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $metric);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($metric);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);;
            $metric = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        $autoOptimizationConfig->setMetrics($metrics);

        /* factors
         * [
         *      <field>
         *      ...
         * ]
         */
        $factors = $autoOptimizationConfig->getFactors();
        foreach ($factors as &$field) {
            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                unset($field);
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        unset($field);

        $autoOptimizationConfig->setFactors($factors);

        /* objective
         * [
         *      <field>
         *      ...
         * ]
         */
        $objective = $autoOptimizationConfig->getObjective();
        $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $objective);
        $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $objective);
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
}
