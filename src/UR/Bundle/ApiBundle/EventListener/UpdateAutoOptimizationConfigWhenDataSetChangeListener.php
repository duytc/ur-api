<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
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

        $newDimensions = $entity->getDimensions();
        $oldDimensions = $entity->getDimensions();
        $newMetrics = $entity->getMetrics();
        $oldMetrics = $entity->getMetrics();

        if ($args->hasChangedField(self::DIMENSIONS_KEY)) {
            $newDimensions = $args->getNewValue(self::DIMENSIONS_KEY);
            $oldDimensions = $args->getOldValue(self::DIMENSIONS_KEY);
        }

        if ($args->hasChangedField(self::METRICS_KEY)) {
            $newMetrics = $args->getNewValue(self::METRICS_KEY);
            $oldMetrics = $args->getOldValue(self::METRICS_KEY);
        }

        $dimensionsMapping = array_combine(array_keys($oldDimensions), array_keys($newDimensions));
        $metricsMapping = array_combine(array_keys($oldMetrics), array_keys($newMetrics));

        $dimensionsMetricsMapping = array_merge($dimensionsMapping, $metricsMapping);

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
            $autoOptimizationConfig = $this->updateOptimizationConfig($autoOptimizationConfig, $entity, $dimensionsMetricsMapping);
            $em->merge($autoOptimizationConfig);
            $em->persist($autoOptimizationConfig);
        }
    }

    private function updateOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, array $dimensionsMetricsMapping)
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
                $field = $this->mappingNewValue($field, $dimensionsMetricsMapping);
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

                    $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
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
                    }
                    foreach ($conditions as &$condition) {
                        $expressions = $condition[self::EXPRESSIONS_KEY];
                        foreach ($expressions as &$expression) {
                            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $expression[self::VAR_KEY]);
                            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $expression[self::VAR_KEY]);

                            if ($dataSetIdFromField != $dataSet->getId()) {
                                continue;
                            }

                            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
                            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

                            $expression[self::VAR_KEY] = $field;

                            unset($field);
                        }
                        $condition[self::EXPRESSIONS_KEY] = $expressions;
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
                        $item = str_replace('[','', $expressionItem);
                        $item = str_replace(']', '', $item);
                        $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $item);
                        $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $item);
                        if ($dataSetIdFromField != $dataSet->getId()) {
                            continue;
                        }

                        $fieldWithoutDataSetId = $this->mappingNewValue(trim($fieldWithoutDataSetId), $dimensionsMetricsMapping);
                        $fieldValue = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
                        $expressionData [] = '['. $fieldValue .']';
                    }

                    $expressionData = implode(' + ', $expressionData);
                    $field[self::EXPRESSION_KEY] = $expressionData;

                    // check defaultValues
                    $defaultValues = [];
                    if (array_key_exists(self::DEFAULT_VALUES_KEY, $field)) {
                        $defaultValues = $field[self::DEFAULT_VALUES_KEY];
                        foreach ($defaultValues as &$defaultValue) {
                            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $defaultValue[self::CONDITION_FIELD_KEY]);
                            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $defaultValue[self::CONDITION_FIELD_KEY]);
                            if ($dataSetIdFromField != $dataSet->getId()) {
                                continue;
                            }

                            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
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

                        $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
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

                    $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
                    $field[self::NUMERATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

                    //denominator
                    $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $field[self::DENOMINATOR_KEY]);
                    $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $field[self::DENOMINATOR_KEY]);
                    if ($dataSetIdFromField != $dataSet->getId()) {
                        continue;
                    }

                    $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
                    $field[self::DENOMINATOR_KEY] = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

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
            $field = $this->mappingNewValue($field, $dimensionsMetricsMapping);
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

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newFieldTypes[$field] = $type;
        }

        $autoOptimizationConfig->setFieldTypes($newFieldTypes);

        /*
         * dimensions
         * [
         *      <field> => <type>,
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
            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $dimension = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
        }

        $autoOptimizationConfig->setDimensions($dimensions);

        /* metrics
         * [
         *      <field> => <type>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfig->getMetrics();
        $newMetrics = [];
        foreach ($metrics as $key => $value) {
            $fieldWithoutDataSetId = preg_replace('(_[0-9]+)', '', $value);
            $dataSetIdFromField = preg_replace('/^(.*)(_)/', '', $value);
            if ($dataSetIdFromField != $dataSet->getId()) {
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);;
            unset($metrics[$key]);
            $value = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newMetrics[$value] = $value;
        }

        $newMetrics = array_merge($newMetrics, $metrics);
        $autoOptimizationConfig->setMetrics($newMetrics);

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

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
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
            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $objective = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);

            $autoOptimizationConfig->setObjective($objective);
        }

        return $autoOptimizationConfig;
    }

    private function mappingNewValue($field, array $mapping)
    {
        return (array_key_exists($field, $mapping)) ? $mapping[$field] : $field;
    }
}