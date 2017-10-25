<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\Core\ReportViewInterface;

class DeleteConditionTransformValueWhenReportViewRemoveTransformListener
{
    /** @var array */
    protected $deletedIds = [];

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        if ($args->hasChangedField(ReportViewInterface::REPORT_VIEW_TRANSFORMS)) {
            $newTransforms = $args->getNewValue(ReportViewInterface::REPORT_VIEW_TRANSFORMS);
            $oldTransforms = $args->getOldValue(ReportViewInterface::REPORT_VIEW_TRANSFORMS);

            $newIds = $this->getAddConditionalTransformValueIdsFromReportViewTransforms($newTransforms);
            $oldIds = $this->getAddConditionalTransformValueIdsFromReportViewTransforms($oldTransforms);

            $deletedIds = array_diff($oldIds, $newIds);
            $this->deletedIds = array_merge($this->deletedIds, $deletedIds);
        }
    }

    /**
     * @param LifecycleEventArgs $args
     * @return mixed|void
     */

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        $deletedIds = $this->getAddConditionalTransformValueIdsFromReportViewTransforms($entity->getTransforms());
        $this->deletedIds = array_merge($this->deletedIds, $deletedIds);
    }


    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (empty($this->deletedIds)) {
            return;
        }

        $em = $args->getEntityManager();
        $repository = $em->getRepository(ReportViewAddConditionalTransformValue::class);

        $copyDeletedIds = $this->deletedIds;
        $this->deletedIds = [];
        $deleted = false;

        foreach ($copyDeletedIds as $deletedId) {
            try {
                $reportViewAddConditionalTransformValue = $repository->find($deletedId);

                if (!$reportViewAddConditionalTransformValue instanceof ReportViewAddConditionalTransformValueInterface) {
                    continue;
                }

                $em->remove($reportViewAddConditionalTransformValue);
                $deleted = true;
            } catch (\Exception $e) {

            }
        }

        if ($deleted) {
            $em->flush();
        }
    }

    /**
     * @param $transforms
     * @return array
     */
    private function getAddConditionalTransformValueIdsFromReportViewTransforms($transforms)
    {
        if (!is_array($transforms) || empty($transforms)) {
            return [];
        }

        $allIds = [];

        foreach ($transforms as $transform) {
            if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform) ||
                !array_key_exists(TransformInterface::FIELDS_TRANSFORM, $transform)
            ) {
                continue;
            }

            if ($transform[TransformInterface::TRANSFORM_TYPE_KEY] == AddConditionValueTransform::TRANSFORMS_TYPE) {
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach ($fields as $field) {
                    if (!array_key_exists(AddConditionValueTransform::VALUES_KEY, $field)) {
                        continue;
                    }

                    $ids = $field[AddConditionValueTransform::VALUES_KEY];

                    if (is_array($ids)) {
                        $allIds = array_merge($allIds, $ids);
                    }
                }
            }
        }

        return $allIds;
    }
}