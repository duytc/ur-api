<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use \Exception;
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
    const NEW_FIELDS_KEY = 'newFields';
    const UPDATED_FIELDS_KEY = 'updateFields';
    const DELETED_FIELDS_KEY = 'deletedFields';
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
     * @throws Exception
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

        if (count($changedFields) == 1 && array_key_exists('numOfPendingLoad', $changedFields)) {
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
            if ($field === 'dimensions') {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if ($field === 'metrics') {
                $this->getChangedFields($values, $renameFields, $newMetrics, $updateMetrics, $deletedMetrics);
            }
        }

        if (count($deletedDimensions) > 0) {
            throw new Exception('cannot delete dimensions');
        }

        $this->changedEntities[] = [
            'dataSet' => $dataSet,
            self::NEW_FIELDS_KEY => array_merge($newDimensions, $newMetrics),
            self::UPDATED_FIELDS_KEY => array_merge($updateDimensions, $updateMetrics),
            self::DELETED_FIELDS_KEY => array_merge($deletedDimensions, $deletedMetrics)
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

            $newFields = $changedEntity[self::NEW_FIELDS_KEY];
            $updateFields = $changedEntity[self::UPDATED_FIELDS_KEY];
            $deletedFields = $changedEntity[self::DELETED_FIELDS_KEY];

            $jobData = [
                self::NEW_FIELDS_KEY => $newFields,
                self::UPDATED_FIELDS_KEY => $updateFields,
                self::DELETED_FIELDS_KEY => $deletedFields
            ];

            // create job to alter data set table
            $dataSetImportJob = DataSetImportJob::createEmptyDataSetImportJob(
                $dataSet,
                null,
                sprintf('alter data set "%s"', $dataSet->getName()),
                DataSetImportJob::JOB_TYPE_ALTER,
                $jobData);
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