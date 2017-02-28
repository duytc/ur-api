<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\TransformType;
use UR\Service\Parser\ImportUtils;
use UR\Worker\Manager;

/**
 * Class DataSetChangeListener
 *
 * Handle event Data Set changed for updating Connected DataSource configuration
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReConfigConnectedDataSourceListener
{
    /**
     * @var array|DataSetInterface[]
     */
    protected $changedEntities = [];

    /**
     * @var array|DataSetInterface[]
     */
    protected $updatedEntity = [];

    protected $deletedFields = [];
    protected $updateFields = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->changedEntities = array_merge($this->changedEntities, $uow->getScheduledEntityUpdates());

        $this->changedEntities = array_filter($this->changedEntities, function ($entity) {
            return $entity instanceof DataSetInterface;
        });
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();
        $entity = $args->getEntity();
        $uow = $em->getUnitOfWork();
        $this->updatedEntity = $entity;

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        // detect changed metrics, dimensions
        $renameFields = [];
        $actions = $entity->getActions();

        if (array_key_exists('rename', $entity->getActions())) {
            $renameFields = $actions['rename'];
        }

        $changedFields = $uow->getEntityChangeSet($entity);
        $newDimensions = [];
        $newMetrics = [];
        $updateDimensions = [];
        $updateMetrics = [];
        $deletedMetrics = [];
        $deletedDimensions = [];

        $uniqueKeyChanges = [];
        foreach ($changedFields as $field => $values) {
            if (strcmp($field, 'dimensions') === 0) {
                $this->getChangedFields($values, $renameFields, $newDimensions, $updateDimensions, $deletedDimensions);
            }

            if (strcmp($field, 'metrics') === 0) {
                $this->getChangedFields($values, $renameFields, $newMetrics, $updateMetrics, $deletedMetrics);
            }

            if (strcmp($field, 'allowOverwriteExistingData') === 0) {
                $uniqueKeyChanges = $values;
            }
        }

        if (count($deletedDimensions) > 0) {
            throw new Exception('cannot delete dimensions');
        }

        $newFields = array_merge($newDimensions, $newMetrics);
        $updateFields = array_merge($updateDimensions, $updateMetrics);
        $deletedFields = array_merge($deletedDimensions, $deletedMetrics);

        // alter data_import table
        $conn = $em->getConnection();
        $importUtils = new ImportUtils();
        $importUtils->alterDataSetTable($entity, $conn, $newFields, $updateFields, $deletedFields, $uniqueKeyChanges);
        $this->updateFields = $updateFields;
        $this->deletedFields = $deletedFields;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        // detect all changed fields
        foreach ($this->changedEntities as $entity) {
            // delete all configs of connected dataSources related to deletedFields
            $connectedDataSources = $entity->getConnectedDataSources();

            foreach ($connectedDataSources as &$connectedDataSource) {
                $this->deleteConfigForConnectedDataSource($connectedDataSource, $this->updateFields, $this->deletedFields);
            }

            $entity->setConnectedDataSources($connectedDataSources);
            $em->merge($entity);
        }

        // reset for new onFlush event
        $this->changedEntities = [];
        $em->flush();
    }

    /**
     * delete Config For Connected DataSource
     *
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param array $updatedFields
     * @param array $deletedFields
     */
    private function deleteConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, array $updatedFields, array $deletedFields)
    {
        $mapFields = $connectedDataSource->getMapFields();
        $requires = $connectedDataSource->getRequires();
//        $duplicates = $connectedDataSource->getDuplicates();
        $filters = $connectedDataSource->getFilters();
        $transforms = $connectedDataSource->getTransforms();

        $delFields = [];
        foreach ($deletedFields as $deletedField => $type) {
            $delFields[] = $deletedField;
        }
        $mapFields = array_diff($mapFields, $delFields);

        foreach ($mapFields as &$mapField) {
            foreach ($updatedFields as $updatedField) {
                if (array_key_exists($mapField, $updatedField)) {
                    $mapField = $updatedField[$mapField];
                }
            }
        }


        $requires = array_values(array_diff($requires, $delFields));
//        $duplicates = array_values(array_diff($duplicates, $delFields));

        foreach ($transforms as $key => &$transform) {
            if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE])) {
                foreach ($delFields as $deletedField) {
                    if (strcmp($deletedField, $transform[TransformType::FIELD]) === 0) {
                        unset($transforms[$key]);
                    }
                }

                foreach ($updatedFields as $updatedField) {
                    if (array_key_exists($transform[TransformType::FIELD], $updatedField)) {
                        $transform[TransformType::FIELD] = $updatedField[$transform[TransformType::FIELD]];
                    }
                }
            } else {
                //SORT BY
                if (strcmp($transform[TransformType::TYPE], TransformType::SORT_BY) === 0) {
                    $count = 0;
                    foreach ($transform[TransformType::FIELDS] as $sortKey => &$fields) {
                        $fields['names'] = array_values(array_diff($fields['names'], $delFields));
                        if (count($fields['names']) < 1) {
                            $count++;
                        }

                        foreach ($fields['names'] as &$field) {
                            foreach ($updatedFields as $updatedField) {
                                if (array_key_exists($field, $updatedField)) {
                                    $field = $updatedField[$field];
                                }
                            }
                        }
                    }

                    if ($count == 2) {
                        unset($transforms[$key]);
                    }

                    continue;
                }

                //ADD FIELD
                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_FIELD) === 0) {
                    $this->updateAddTypeTransform($transform, $delFields, $updatedFields, $transforms, $key);
                }

                //ADD CALCULATED FIELD
                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CALCULATED_FIELD) === 0) {
                    $this->updateAddTypeTransform($transform, $delFields, $updatedFields, $transforms, $key);
                }

                //COMPARISON PERCENT
                if (strcmp($transform[TransformType::TYPE], TransformType::COMPARISON_PERCENT) === 0) {
                    $this->updateAddTypeTransform($transform, $delFields, $updatedFields, $transforms, $key);
                }

                if (count($transforms[$key][TransformType::FIELDS]) === 0) {
                    unset($transforms[$key]);
                }
            }
        }

        $connectedDataSource->setMapFields($mapFields);
        $connectedDataSource->setRequires(array_values($requires));
//        $connectedDataSource->setDuplicates(array_values($duplicates));
        $connectedDataSource->setFilters(array_values($filters));
        $connectedDataSource->setTransforms(array_values($transforms));
    }

    public function getChangedFields(array $values, array $renameFields, array &$newDimensions, array &$updateDimensions, array &$deletedFields)
    {
        $deletedFields = array_diff_key($values[0], $values[1]);
        foreach ($renameFields as $renameField) {
            if (!array_key_exists('from', $renameField) || !array_key_exists('to', $renameField)) {
                continue;
            }

            $oldFieldName = $renameField['from'];
            $newFieldName = $renameField['to'];

            if (array_key_exists($oldFieldName, $deletedFields)) {
                $updateDimensions[] = [$oldFieldName => $newFieldName];
                unset($deletedFields[$oldFieldName]);
            }
        }

        $newDimensions = array_diff_key($values[1], $values[0]);
        foreach ($updateDimensions as $updateDimension) {
            foreach ($updateDimension as $item) {
                if (array_key_exists($item, $newDimensions)) {
                    unset($newDimensions[$item]);
                }
            }
        }
    }

    public function updateAddTypeTransform(array &$transform, array $delFields, array $updatedFields, array &$transforms, &$key)
    {
        foreach ($transform[TransformType::FIELDS] as $k => &$field) {
            foreach ($delFields as $deletedField) {
                if (strcmp($field[TransformType::FIELD], $deletedField) === 0) {
                    unset($transforms[$key][TransformType::FIELDS][$k]);
                }
            }

            foreach ($updatedFields as $updatedField) {
                if (array_key_exists($field[TransformType::FIELD], $updatedField)) {
                    $field[TransformType::FIELD] = $updatedField[$field[TransformType::FIELD]];
                }
            }
        }

        $transforms[$key][TransformType::FIELDS] = array_values($transforms[$key][TransformType::FIELDS]);
    }
}