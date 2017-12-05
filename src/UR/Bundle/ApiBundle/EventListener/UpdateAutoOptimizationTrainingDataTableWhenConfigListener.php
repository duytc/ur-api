<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;

class UpdateAutoOptimizationTrainingDataTableWhenConfigListener
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$entity instanceof AutoOptimizationConfigInterface) {
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
        $dimensionsMapping = array_combine($oldDimensions, $newDimensions);
        $metricsMapping = array_combine($oldMetrics, $newMetrics);
        $dimensionsMetricsMapping = array_merge($dimensionsMapping, $metricsMapping);
        /** @var AutoOptimizationConfigDataSetInterface[]|Collection $autoOptimizationConfigDataSets */
        $autoOptimizationConfigDataSets = $entity->getAutoOptimizationConfigDataSets();
        if ($autoOptimizationConfigDataSets instanceof Collection) {
            $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets->toArray();
        }

        foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
            $autoOptimizationConfigDataSet = $this->updateOptimizationTrainingTable($autoOptimizationConfigDataSet, $dimensionsMetricsMapping);
            $em->merge($autoOptimizationConfigDataSet);
            $em->persist($autoOptimizationConfigDataSet);
        }
    }

    private function updateOptimizationTrainingTable(AutoOptimizationConfigDataSetInterface $autoOptimizationConfigDataSet, array $dimensionsMetricsMapping)
    {
        /* filters */
        $filters = $autoOptimizationConfigDataSet->getFilters();
        foreach ($filters as &$filter) {
            if (!array_key_exists(AbstractFilter::FILTER_DATA_SET_KEY, $filter)) {
                continue;
            }
            $dataSetId = $filter[AbstractFilter::FILTER_DATA_SET_KEY];
            if ($dataSetId !== $autoOptimizationConfigDataSet->getDataSet()->getId()) {
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
        $autoOptimizationConfigDataSet->setFilters($filters);
        /*
         * dimensions
         * [
         *      <field> => <type>,
         *      ...
         * ]
         */
        $dimensions = $autoOptimizationConfigDataSet->getDimensions();
        $newDimensions = [];
        foreach ($dimensions as $field => $type) {
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
            if ($dataSetIdFromField != $autoOptimizationConfigDataSet->getDataSet()->getId()) {
                $newDimensions[$field] = $type;
                continue;
            }
            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newDimensions[$field] = $type;
        }
        $autoOptimizationConfigDataSet->setDimensions($newDimensions);
        /* metrics
         * [
         *      <field> => <type>,
         *      ...
         * ]
         */
        $metrics = $autoOptimizationConfigDataSet->getMetrics();
        $newMetrics = [];
        foreach ($metrics as $field => $type) {
            $fieldWithoutDataSetId = preg_replace('/^(.*)(_)(\d)$/', '$1', $field);
            $dataSetIdFromField = preg_replace('/^(.*)(_)(\d)$/', '$3', $field);
            if ($dataSetIdFromField != $autoOptimizationConfigDataSet->getDataSet()->getId()) {
                $newMetrics[$field] = $type;
                continue;
            }
            $fieldWithoutDataSetId = $this->mappingNewValue($fieldWithoutDataSetId, $dimensionsMetricsMapping);
            $field = sprintf('%s_%d', $fieldWithoutDataSetId, $dataSetIdFromField);
            $newMetrics[$field] = $type;
        }
        $autoOptimizationConfigDataSet->setMetrics($newMetrics);

        return $autoOptimizationConfigDataSet;
    }

    private function mappingNewValue($field, array $mapping)
    {
        return (array_key_exists($field, $mapping)) ? $mapping[$field] : $field;
    }
}