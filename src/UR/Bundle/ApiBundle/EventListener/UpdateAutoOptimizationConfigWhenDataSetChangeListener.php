<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Behaviors\AutoOptimizationUtilTrait;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;

class UpdateAutoOptimizationConfigWhenDataSetChangeListener
{
    use AutoOptimizationUtilTrait;

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

        $autoOptimizationConfigs = [];
        foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
            $autoOptimizationConfigDataSet = $this->updateOptimizationConfigDataSet($autoOptimizationConfigDataSet, $updateFields, $deleteFields);
            $em->merge($autoOptimizationConfigDataSet);
            $em->persist($autoOptimizationConfigDataSet);

            $autoOptimizationConfig = $autoOptimizationConfigDataSet->getAutoOptimizationConfig();
            if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
                continue;
            }
            $autoOptimizationConfigs[$autoOptimizationConfig->getId()] = $autoOptimizationConfig;
        }

        foreach ($autoOptimizationConfigs as $autoOptimizationConfig) {
            $autoOptimizationConfig = $this->updateOptimizationConfig($autoOptimizationConfig, $entity, $updateFields, $deleteFields);
            $em->merge($autoOptimizationConfig);
            $em->persist($autoOptimizationConfig);
        }
    }

    private function updateOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig, DataSetInterface $dataSet, array $updateFields, array $deleteFields)
    {
        $dimensions = $this->updateFieldsWhenDataSetChange($autoOptimizationConfig->getDimensions(), $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig->setDimensions($dimensions);

        $metrics = $this->updateFieldsWhenDataSetChange($autoOptimizationConfig->getMetrics(), $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig->setMetrics($metrics);

        $factors = $this->updateFieldsWhenDataSetChange($autoOptimizationConfig->getFactors(), $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig->setFactors($factors);

        $positiveFactors = $this->updateFieldsWhenDataSetChange($autoOptimizationConfig->getPositiveFactors(), $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig->setPositiveFactors($positiveFactors);

        $negativeFactors = $this->updateFieldsWhenDataSetChange($autoOptimizationConfig->getNegativeFactors(), $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig->setNegativeFactors($negativeFactors);

        $autoOptimizationConfig = $this->updateJoinByWhenDataSetChange($autoOptimizationConfig, $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig = $this->updateTransformsWhenDataSetChange($autoOptimizationConfig, $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig = $this->updateFieldTypeWhenDataSetChange($autoOptimizationConfig, $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig = $this->updateObjectiveWhenDataSetChange($autoOptimizationConfig, $dataSet, $updateFields, $deleteFields);
        $autoOptimizationConfig = $this->updateIdentifiersWhenDataSetChange($autoOptimizationConfig, $dataSet, $updateFields, $deleteFields);

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