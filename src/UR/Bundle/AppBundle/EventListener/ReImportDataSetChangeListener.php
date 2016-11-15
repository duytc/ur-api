<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\ModelInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\TransformType;
use UR\Service\Parser\ImportUtils;
use UR\Worker\Manager;

/**
 * Class DataSetChangeListener
 *
 * Handle event Data Set changed for updating
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportDataSetChangeListener
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

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->changedEntities = array_merge($this->changedEntities, $uow->getScheduledEntityUpdates());

        $this->changedEntities = array_filter($this->changedEntities, function ($entity) {
            return $entity instanceof DataSetInterface;
        });
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        // detect all changed fields
        foreach ($this->changedEntities as $entity) {
            if (!$entity instanceof DataSetInterface) {
                continue;
            }

            // detect changed metrics, dimensions
            $changedFields = $uow->getEntityChangeSet($entity);
            $deletedMetrics = [];
            $deletedDimensions = [];
            $newDimensions = [];
            $newMetrics = [];

            foreach ($changedFields as $field => $values) {
                if (strcmp($field, 'dimensions') === 0) {
                    array_diff($values[0], $values[1]);
                    $deletedDimensions = array_diff_key($values[0], $values[1]);
                    $newDimensions = array_diff_key($values[1], $values[0]);
                }

                if (strcmp($field, 'metrics') === 0) {
                    array_diff($values[0], $values[1]);
                    $deletedMetrics = array_diff_key($values[0], $values[1]);
                    $newMetrics = array_diff_key($values[1], $values[0]);
                }
            }

            $deletedFields = array_merge($deletedDimensions, $deletedMetrics);
            $newFields = array_merge($newDimensions, $newMetrics);

            // alter data_import table
            $conn = $em->getConnection();
            $importUtils = new ImportUtils();
            $importUtils->alterDataSetTable($entity, $conn, $deletedFields, $newFields);

            // delete all configs of connected dataSources related to deletedFields
            $connectedDataSources = $entity->getConnectedDataSources();

            foreach ($connectedDataSources as &$connectedDataSource) {
                $this->deleteConfigForConnectedDataSource($connectedDataSource, $deletedFields);
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
     * @param array $deletedFields
     */
    private function deleteConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, array $deletedFields)
    {
        $mapFields = $connectedDataSource->getMapFields();
        $requires = $connectedDataSource->getRequires();
        $filters = $connectedDataSource->getFilters();
        $transforms = $connectedDataSource->getTransforms();

        foreach ($deletedFields as $deletedField => $type) {
            if (in_array($deletedField, $mapFields)) {
                if (($key = array_search($deletedField, $mapFields)) !== false) {
                    unset($mapFields[$key]);
                }
            }

            if (in_array($deletedField, $requires)) {
                if (($key = array_search($deletedField, $requires)) !== false) {
                    unset($requires[$key]);
                }
            }

            foreach ($filters as $key => $filter) {
                if (strcmp($deletedField, $filter[FilterType::FIELD]) === 0) {
                    unset($filters[$key]);
                }
            }

            foreach ($transforms as $key => $transform) {
                if (TransformType::isTransformSingleField($transform[TransformType::TRANSFORM_TYPE])) {
                    if (strcmp($deletedField, $transform[TransformType::FIELD]) === 0) {
                        unset($transforms[$key]);
                    }
                } else {
                    if (TransformType::isGroupOrSortType($transform[TransformType::TYPE])) {
                        foreach ($transform[TransformType::FIELDS] as $k => $value) {
                            if (strcmp($value, $deletedField) === 0) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }
                        }
                    }
                    if (TransformType::isAddingType($transform[TransformType::TYPE])) {
                        foreach ($transform[TransformType::FIELDS] as $k => $field) {
                            if (strcmp($field[TransformType::FIELD], $deletedField) === 0) {
                                unset($transforms[$key][$k]);
                            }
                        }

                        if (strcmp($transform[TransformType::TYPE], TransformType::COMPARISON_PERCENT) === 0) {
                            foreach ($transform[TransformType::FIELDS] as $k => $field) {
                                if (in_array($deletedField, $field[TransformType::COMPARISON])) {
                                    unset($transforms[$key]);
                                }
                            }
                        }
                    }
                }
            }
        }

        $connectedDataSource->setMapFields($mapFields);
        $connectedDataSource->setRequires($requires);
        $connectedDataSource->setFilters($filters);
        $connectedDataSource->setTransforms($transforms);
    }
}