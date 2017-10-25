<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewAddConditionalTransformValueRepositoryInterface;

class UpdateConditionTransformValueWhenReportViewChangeDimensionsMetricsListener
{
	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
    const TRANSFORM_TYPE_KEY = 'type';
    const ADD_CONDITION_VALUE_TRANSFORM_TYPE = 'addConditionalTransformValue';
    const ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY = 'values';

	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();
        $em = $args->getEntityManager();

		if (!$entity instanceof ReportViewInterface) {
			return;
		}

		if ($args->hasChangedField(self::DIMENSIONS_KEY) || $args->hasChangedField(self::METRICS_KEY)) {
            $newDimensions = $args->getNewValue(self::DIMENSIONS_KEY);
            $newMetrics = $args->getNewValue(self::METRICS_KEY);
            $oldDimensions = $args->getOldValue(self::DIMENSIONS_KEY);
            $oldMetrics = $args->getOldValue(self::METRICS_KEY);

            $newDimensionsMetrics = array_merge($newDimensions, $newMetrics);
            $oldDimensionsMetrics = array_merge($oldMetrics, $oldDimensions);

            $newValues = [];
            $oldValues = [];

            foreach ($newDimensionsMetrics as $key => $value) {
                foreach ($oldDimensionsMetrics as $oldKey => $oldValue) {
                    if ($key == $oldKey && $value != $oldValue) {
                        $newValues [] = $value;
                        $oldValues [] = $oldValue;
                    }
                }
            }

            // update field type in add condition transform value when the system has the changes dimensions or metrics of reportView
            $transforms = $entity->getTransforms();

            if (is_null($transforms)) {
                return;
            }

            foreach ($transforms as $transform) {
                //$transform = json_decode($transform, true);
                if ($transform[self::TRANSFORM_TYPE_KEY] === self::ADD_CONDITION_VALUE_TRANSFORM_TYPE) {
                    // $ids = [1, 2, 3];
                    $ids = $transform[self::ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY];

                    // foreach $ids -> get addConditionValueTransformValue
                    foreach ($ids as $id){
                        /** @var ReportViewAddConditionalTransformValueRepositoryInterface $reportViewAddConditionalTransformValueRepository */
                        $reportViewAddConditionalTransformValueRepository = $em->getRepository(ReportViewAddConditionalTransformValue::class);

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
                                $newValueField = $sharedConditional['conditionField'];
                                if(in_array($sharedConditional['conditionField'], $oldValues)){
                                    // get new field name
                                    foreach ($newValues as $key => $value) {
                                        foreach ($oldValues as $oldKey => $oldValue) {
                                            if ($key == $oldKey && $sharedConditional['conditionField'] == $oldValue) {
                                                $newValueField = $value;
                                            }
                                        }
                                    }
                                    $sharedConditional['conditionField'] = $newValueField;
                                }
                            }
                        }

                        $reportViewAddConditionalTransformValue->setSharedConditions($sharedConditionals);

                        // replace conditions
                        $conditions = $reportViewAddConditionalTransformValue->getConditions();
                        if (!empty($conditions) && is_array($conditions)) {
                            foreach ($conditions as &$condition) {
                                //if ($sharedConditional['conditionField'] )
                                if(!empty($condition['expressions']) && is_array($condition['expressions'])){
                                    foreach ($condition['expressions'] as &$expression) {
                                        $newValueField = $expression['conditionField'];
                                        if(in_array($expression['conditionField'], $oldValues)){
                                            foreach ($newValues as $key => $value) {
                                                foreach ($oldValues as $oldKey => $oldValue) {
                                                    if ($key == $oldKey && $expression['conditionField'] == $oldValue) {
                                                        $newValueField = $value;
                                                    }
                                                }
                                            }
                                            $expression['conditionField'] = $newValueField;
                                        }
                                    }
                                }

                            }
                        }

                        $reportViewAddConditionalTransformValue->setConditions($conditions);

                        // save addConditionValuewTransformValue
                        $em->persist($reportViewAddConditionalTransformValue);
                        $em->flush();
                    }
                }

            }
		}
	}
}