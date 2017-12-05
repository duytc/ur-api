<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Report\SqlBuilder;

class UpdateAutoOptimizationConfigWhenDataSetChangeListener
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

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

        $dimensionsMapping = array_merge($oldDimensions, $newDimensions);
        $metricsMapping = array_merge($oldMetrics, $newMetrics);

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
            $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
            foreach ($fields as &$field) {
                if (is_array($field)) {
                    if (array_key_exists('field', $field)) {
                        $field = $field['field'];
                    }
                    if (array_key_exists('names', $field)) {
                        $field = $field['names'][0];
                    }
                }
                $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
                $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
                if ($dataSetIdFromField != $dataSet->getId()) {
                    continue;
                }

                $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
                $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            }

            $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;

            unset($field);
        }

        unset($transform);

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
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
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
        $newDimensions = [];
        foreach ($dimensions as $field => $type) {
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                $newDimensions[$field] = $type;
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newDimensions[$field] = $type;
        }

        $autoOptimizationConfig->setDimensions($newDimensions);

        /* metrics
         * [
         *      <field> => <type>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfig->getMetrics();
        $newMetrics = [];
        foreach ($metrics as $field => $type) {
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
            if ($dataSetIdFromField != $dataSet->getId()) {
                $newMetrics[$field] = $type;
                continue;
            }

            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newMetrics[$field] = $type;
        }

        $autoOptimizationConfig->setMetrics($newMetrics);

        /* factors
         * [
         *      <field>
         *      ...
         * ]
         */
        $factors = $autoOptimizationConfig->getFactors();
        foreach ($factors as &$field) {
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
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
        $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $objective);
        $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $objective);
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