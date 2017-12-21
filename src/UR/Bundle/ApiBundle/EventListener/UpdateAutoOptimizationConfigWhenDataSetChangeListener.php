<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Repository\Core\ReportViewAddConditionalTransformValueRepositoryInterface;
use UR\Service\Report\SqlBuilder;

class UpdateAutoOptimizationConfigWhenDataSetChangeListener
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';
    const RENAME_KEY = 'rename';
    const FROM_KEY = 'from';
    const TO_KEY = 'to';
    const EXPRESSIONS_KEY = 'expressions';
    const EXPRESSION_KEY = 'expression';
    const CONDITIONS_KEY = 'conditions';
    const CONDITION_FIELD_KEY = 'conditionField';
    const NAMES_KEY = 'names';
    const DENOMINATOR_KEY = 'denominator';
    const NUMERATOR_KEY = 'numerator';
    const VAR_KEY = 'var';
    const DEFAULT_VALUES_KEY = 'defaultValues';

    /** @var  EntityManagerInterface */
    private $em;

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        $this->em = $em;

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        // get changes
        if (!$args->hasChangedField(self::DIMENSIONS_KEY) && !$args->hasChangedField(self::METRICS_KEY)) {
            return;
        }

        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($entity);

        if (!array_key_exists(self::DIMENSIONS_KEY, $changedFields) && !array_key_exists(self::METRICS_KEY, $changedFields)) {
            return;
        }
        // detect changed metrics, dimensions
        $renameFields = [];
        $actions = $entity->getActions() === null ? [] : $entity->getActions();

        if (array_key_exists(self::RENAME_KEY, $actions)) {
            $renameFields = $actions[self::RENAME_KEY];
        }

        $newDimensions = [];
        $newMetrics = [];
        $updateDimensions = [];
        $updateMetrics = [];
        $deletedMetrics = [];
        $deletedDimensions = [];

        foreach ($changedFields as $field => $values) {
            if ($field === self::DIMENSIONS_KEY) {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if ($field === self::METRICS_KEY) {
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

        foreach ($autoOptimizationConfigs as $autoOptimizationConfig) {
            $autoOptimizationConfig = $this->updateOptimizationConfig($autoOptimizationConfig, $entity, $updateFields, $deleteFields);
            $em->merge($autoOptimizationConfig);
            $em->persist($autoOptimizationConfig);
            //addTransformVAlue
            $this->updateOptimizationConfigTransformAddConditionValue($autoOptimizationConfig, $updateFields, $deleteFields);
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
                                list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($expression[self::VAR_KEY]);
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
                    $field[self::EXPRESSION_KEY] = $expressionData;

                    // check defaultValues
                    if (array_key_exists(self::DEFAULT_VALUES_KEY, $field)) {
                        $defaultValues = $field[self::DEFAULT_VALUES_KEY];
                        foreach ($defaultValues as &$defaultValue) {
                            list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($defaultValue[self::CONDITION_FIELD_KEY]);
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
                    $field[self::NAMES_KEY] = $fieldNames;

                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

                    unset($field, $fieldNames);
                }
            }

            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::COMPARISON_PERCENT_TRANSFORM) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as &$field) {
                    //numerator
                    list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field[self::NUMERATOR_KEY]);
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
                    list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($field[self::DENOMINATOR_KEY]);
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

        $autoOptimizationConfig->setDimensions($dimensions);

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

        $autoOptimizationConfig->setMetrics($metrics);

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
        $autoOptimizationConfig->setFactors($factors);

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
        $autoOptimizationConfigDataSet->setFilters($filters);
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

        $autoOptimizationConfigDataSet->setDimensions($dimensions);

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

        $autoOptimizationConfigDataSet->setMetrics($metrics);

        return $autoOptimizationConfigDataSet;
    }

    private function updateOptimizationConfigTransformAddConditionValue(AutoOptimizationConfigInterface $autoOptimizationConfig, array $updateFields, array $deleteFields)
    {
        /*
         *  {
        "fields":[
            {
                "values":[
                    4
                ],
                "field":"addConditionalValue",
                "defaultValue":"abc",
                "type":"text"
            }
        ],
        "type":"addConditionValue",
        "isPostGroup":true
        }
         */
        $transforms = $autoOptimizationConfig->getTransforms();
        foreach ($transforms as $transform) {
            if (is_array($transform) && $transform[GroupByTransform::TRANSFORM_TYPE_KEY] == GroupByTransform::ADD_CONDITION_VALUE_TRANSFORM) {

                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as $field) {
                    // $ids = [1, 2, 3];
                    $ids = $field['values'];

                    // foreach $ids -> get addConditionValueTransformValue
                    foreach ($ids as $id){
                        /** @var ReportViewAddConditionalTransformValueRepositoryInterface $reportViewAddConditionalTransformValueRepository */
                        $reportViewAddConditionalTransformValueRepository = $this->em->getRepository(ReportViewAddConditionalTransformValue::class);

                        $reportViewAddConditionalTransformValue = $reportViewAddConditionalTransformValueRepository->find($id);

                        if (!$reportViewAddConditionalTransformValue instanceof ReportViewAddConditionalTransformValueInterface) {
                            continue;
                        }
                        // -> and then change field name according the changes dimensions and metric of reportView
                        //replace sharedConditionals
                        $sharedConditionals = $reportViewAddConditionalTransformValue->getSharedConditions();
                        if (!empty($sharedConditionals) && is_array($sharedConditionals)) {
                            foreach ($sharedConditionals as &$sharedConditional) {
                                //if ($sharedConditional['conditionField'] )
                                if (!is_array($sharedConditional) || !array_key_exists('field', $sharedConditional)) {
                                    continue;
                                }
                                $valueField = $sharedConditional['field'];
                                list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($valueField);
                                if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                    unset($sharedConditional);
                                    continue;
                                }

                                $valueFieldUpdate = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                $valueFieldUpdate = sprintf('%s_%d', $valueFieldUpdate, $dataSetIdFromField);
                                $sharedConditional['field'] = $valueFieldUpdate;
                            }
                        }

                        $reportViewAddConditionalTransformValue->setSharedConditions($sharedConditionals);
                        unset($sharedConditional, $sharedConditionals);

                        // replace conditions
                        $conditions = $reportViewAddConditionalTransformValue->getConditions();
                        if (!empty($conditions) && is_array($conditions)) {
                            foreach ($conditions as &$condition) {
                                //if ($sharedConditional['conditionField'] )
                                if(!empty($condition[self::EXPRESSIONS_KEY]) && is_array($condition[self::EXPRESSIONS_KEY])){
                                    foreach ($condition[self::EXPRESSIONS_KEY] as &$expression) {
                                        if (!is_array($expression) || !array_key_exists('field', $expression)) {
                                            continue;
                                        }
                                        $valueField =  $expression['field'];
                                        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($valueField);
                                        if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                            unset($expression);
                                            continue;
                                        }

                                        $valueFieldUpdate = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                        $valueFieldUpdate = sprintf('%s_%d', $valueFieldUpdate, $dataSetIdFromField);
                                        $expression['field'] = $valueFieldUpdate;
                                    }
                                }

                            }
                        }
                        $reportViewAddConditionalTransformValue->setConditions($conditions);
                        unset($conditions, $condition, $expression);

                        // save addConditionValuewTransformValue
                        $this->em->merge($reportViewAddConditionalTransformValue);
                        $this->em->persist($reportViewAddConditionalTransformValue);
                    }
                }

            }
        }
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
            if (!array_key_exists(self::FROM_KEY, $renameField) || !array_key_exists(self::TO_KEY, $renameField)) {
                continue;
            }

            $oldFieldName = $renameField[self::FROM_KEY];
            $newFieldName = $renameField[self::TO_KEY];

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
