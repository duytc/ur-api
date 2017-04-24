<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Model\Core\DataSetImportJob;
use UR\Model\Core\DataSetInterface;
use UR\Worker\Manager;

/**
 * Class DataSetChangeListener
 *
 * Handle event Data Set changed for updating Connected DataSource configuration
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class AlterDataSetListener
{
    /**
     * @var array|DataSetInterface[]
     */
    protected $changedEntities = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    /**
     * handle event postUpdate to detect all data sets changed
     *
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $args->getEntity();
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($dataSet);

        if (!array_key_exists('dimensions', $changedFields) && !array_key_exists('metrics', $changedFields)) {
            return;
        }

        // detect changed metrics, dimensions
        $renameFields = [];
        $actions = $dataSet->getActions() === null ? [] : $dataSet->getActions();

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
            if (strcmp($field, 'dimensions') === 0) {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if (strcmp($field, 'metrics') === 0) {
                $this->getChangedFields($values, $renameFields, $newMetrics, $updateMetrics, $deletedMetrics);
            }
        }

        if (count($deletedDimensions) > 0) {
            throw new Exception('cannot delete dimensions');
        }

        $this->changedEntities[] = [
            'dataSet' => $dataSet,
            'newFields' => array_merge($newDimensions, $newMetrics),
            'updateFields' => array_merge($updateDimensions, $updateMetrics),
            'deletedFields' => array_merge($deletedDimensions, $deletedMetrics)
        ];
    }

    /**
     * handle event postFlush to handle all data sets changed that are detected by postUpdate before
     *
     * Notice: this listener has higher priority than postFlush of ReConfigConnectedDataSource.
     * This make sure data set table is altered before applying changes to it's connected data sources
     *
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        $em = $args->getEntityManager();

        // temporarily create all alter Data Set Import Jobs descriptions
        $alterDataSetJobDescriptions = [];

        foreach ($this->changedEntities as $changedEntity) {
            /** @var DataSetInterface $dataSet */
            $dataSet = $changedEntity['dataSet'];
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $newFields = $changedEntity['newFields'];
            $updateFields = $changedEntity['updateFields'];
            $deletedFields = $changedEntity['deletedFields'];

            // create job to alter data set table
            $dataSetImportJob = DataSetImportJob::createEmptyDataSetImportJob($dataSet, 'alter data set');
            $em->persist($dataSetImportJob);

            // add to list
            $alterDataSetJobDescriptions[] = [
                'dataSetId' => $dataSet->getId(),
                'newFields' => $newFields,
                'updateFields' => $updateFields,
                'deletedFields' => $deletedFields,
                'dataSetImportJobId' => $dataSetImportJob->getJobId()
            ];
        }

        // reset for new onFlush event
        $this->changedEntities = [];

        // persist all dataSetImportJobs before creating alter data set jobs
        $em->flush();

        // connected data source change too

        // ...

        // create all alter data set jobs
        foreach ($alterDataSetJobDescriptions as $alterDataSetJobDescription) {
            $dataSetId = $alterDataSetJobDescription['dataSetId'];
            $newFields = $alterDataSetJobDescription['newFields'];
            $updateFields = $alterDataSetJobDescription['updateFields'];
            $deletedFields = $alterDataSetJobDescription['deletedFields'];
            $dataSetImportJobId = $alterDataSetJobDescription['dataSetImportJobId'];

            $this->workerManager->alterDataSetTable($dataSetId, $newFields, $updateFields, $deletedFields, $dataSetImportJobId);
        }
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