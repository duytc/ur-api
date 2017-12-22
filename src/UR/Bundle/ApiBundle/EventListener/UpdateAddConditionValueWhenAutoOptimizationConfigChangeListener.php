<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Repository\Core\ReportViewAddConditionalTransformValueRepositoryInterface;

class UpdateAddConditionValueWhenAutoOptimizationConfigChangeListener
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';
    const RENAME_KEY = 'rename';
    const FROM_KEY = 'from';
    const TO_KEY = 'to';
    const EXPRESSIONS_KEY = 'expressions';

    /** @var  EntityManagerInterface */
    private $em;

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        $this->em = $em;

        if (!$entity instanceof DataSetInterface) {
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
            $this->updateOptimizationConfigTransformAddConditionValue($autoOptimizationConfig, $updateFields, $deleteFields);
        }
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $updateFields
     * @param array $deleteFields
     */
    private function updateOptimizationConfigTransformAddConditionValue(AutoOptimizationConfigInterface $autoOptimizationConfig, array $updateFields, array $deleteFields)
    {
        /*
        {
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
                    $ids = $field['values']; // $ids = [1, 2, 3];
                    // foreach $ids -> get addConditionValueTransformValue
                    foreach ($ids as $id){
                        /** @var ReportViewAddConditionalTransformValueRepositoryInterface $reportViewAddConditionalTransformValueRepository */
                        $reportViewAddConditionalTransformValueRepository = $this->em->getRepository(ReportViewAddConditionalTransformValue::class);

                        $reportViewAddConditionalTransformValue = $reportViewAddConditionalTransformValueRepository->find($id);

                        if (!$reportViewAddConditionalTransformValue instanceof ReportViewAddConditionalTransformValueInterface) {
                            continue;
                        }
                        //replace sharedConditionals
                        $sharedConditionals = $reportViewAddConditionalTransformValue->getSharedConditions();
                        $newSharedConditionals = [];
                        if (!empty($sharedConditionals) && is_array($sharedConditionals)) {
                            foreach ($sharedConditionals as $key => $sharedConditional) {
                                //if ($sharedConditional['conditionField'] )
                                if (!is_array($sharedConditional) || !array_key_exists('field', $sharedConditional)) {
                                    continue;
                                }
                                $valueField = $sharedConditional['field'];
                                list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($valueField);
                                if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                    unset($sharedConditionals[$key]);
                                    continue;
                                }

                                $valueFieldUpdate = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                $valueFieldUpdate = sprintf('%s_%d', $valueFieldUpdate, $dataSetIdFromField);
                                $sharedConditional['field'] = $valueFieldUpdate;
                                $newSharedConditionals []= $sharedConditional;
                            }
                        }
                        $reportViewAddConditionalTransformValue->setSharedConditions($newSharedConditionals);
                        unset($sharedConditional, $sharedConditionals, $newSharedConditionals);

                        // replace conditions
                        $conditions = $reportViewAddConditionalTransformValue->getConditions();

                        if (!empty($conditions) && is_array($conditions)) {
                            foreach ($conditions as $keyCondition => $condition) {
                                $newCondition = [];
                                if(!empty($condition[self::EXPRESSIONS_KEY]) && is_array($condition[self::EXPRESSIONS_KEY])){
                                    foreach ($condition[self::EXPRESSIONS_KEY] as $key => $expression) {
                                        if (!is_array($expression) || !array_key_exists('field', $expression)) {
                                            continue;
                                        }
                                        $valueField =  $expression['field'];
                                        list($fieldWithoutDataSetId, $dataSetIdFromField) = $this->getFieldNameAndDataSetId($valueField);
                                        if ($this->deleteFieldValue($fieldWithoutDataSetId, $deleteFields)) {
                                            unset($condition[self::EXPRESSIONS_KEY][$key]);
                                            continue;
                                        }

                                        $valueFieldUpdate = $this->mappingNewValue($fieldWithoutDataSetId, $updateFields);
                                        $valueFieldUpdate = sprintf('%s_%d', $valueFieldUpdate, $dataSetIdFromField);
                                        $expression['field'] = $valueFieldUpdate;

                                        $condition[self::EXPRESSIONS_KEY][$key] = $expression;
                                    }

                                    if (!empty($condition[self::EXPRESSIONS_KEY])) {
                                        $newCondition[] = $condition;
                                    }
                                }
                                if (!empty($newCondition)) {
                                    $conditions[$keyCondition] = $newCondition;
                                } else {
                                    unset($conditions[$keyCondition]);
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
