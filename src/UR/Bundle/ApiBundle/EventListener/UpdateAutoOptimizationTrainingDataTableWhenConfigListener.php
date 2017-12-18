<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\DataSetInterface;

class UpdateAutoOptimizationTrainingDataTableWhenConfigListener
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
//        /** @var AutoOptimizationConfigDataSetInterface[]|Collection $autoOptimizationConfigDataSets */
//        $autoOptimizationConfigDataSets = $entity->getAutoOptimizationConfigDataSets();
//        if ($autoOptimizationConfigDataSets instanceof Collection) {
//            $autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets->toArray();
//        }
//
//        foreach ($autoOptimizationConfigDataSets as $autoOptimizationConfigDataSet) {
//            $autoOptimizationConfigDataSet = $this->updateOptimizationTrainingTable($autoOptimizationConfigDataSet, $updateFields, $deleteFields);
//            $em->merge($autoOptimizationConfigDataSet);
//            $em->persist($autoOptimizationConfigDataSet);
//        }
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