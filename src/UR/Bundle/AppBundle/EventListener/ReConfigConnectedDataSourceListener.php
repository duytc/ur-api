<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
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

    protected $deletedFields;

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
                $this->deleteConfigForConnectedDataSource($connectedDataSource, $this->deletedFields);
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
        $duplicates = $connectedDataSource->getDuplicates();
        $filters = $connectedDataSource->getFilters();
        $transforms = $connectedDataSource->getTransforms();

        $delFields = [];
        foreach ($deletedFields as $deletedField => $type) {
            $delFields[] = $deletedField;
        }
        $mapFields = array_diff($mapFields, $delFields);
        $requires = array_values(array_diff($requires, $delFields));
        $duplicates = array_values(array_diff($duplicates, $delFields));

        foreach ($delFields as $deletedField) {

            foreach ($filters as $key => $filter) {
                if (strcmp($deletedField, $filter[FilterType::FIELD]) === 0) {
                    unset($filters[$key]);
                }
            }
        }

        foreach ($transforms as $key => $transform) {
            if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE])) {
                foreach ($delFields as $deletedField) {
                    if (strcmp($deletedField, $transform[TransformType::FIELD]) === 0) {
                        unset($transforms[$key]);
                    }
                }
            } else {
                //GROUP BY
                if (strcmp($transform[TransformType::TYPE], TransformType::GROUP_BY) === 0) {
                    $transforms[$key][TransformType::FIELDS] = array_values(array_diff($transforms[$key][TransformType::FIELDS], $delFields));
                }

                //SORT BY
                if (strcmp($transform[TransformType::TYPE], TransformType::SORT_BY) === 0) {
                    $count = 0;
                    foreach ($transform[TransformType::FIELDS] as $sortKey => $fields) {

                        $transforms[$key][TransformType::FIELDS][$sortKey]['names'] = array_values(array_diff($transforms[$key][TransformType::FIELDS][$sortKey]['names'], $delFields));
                        if (count($transforms[$key][TransformType::FIELDS][$sortKey]['names']) < 1) {
                            $count++;
                        }
                    }
                    if ($count == 2) {
                        unset($transforms[$key]);
                    }
                    continue;
                }

                //ADD FIELD
                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_FIELD) === 0) {
                    foreach ($transform[TransformType::FIELDS] as $k => $field) {
                        foreach ($delFields as $deletedField) {
                            if (strcmp($field[TransformType::FIELD], $deletedField) === 0) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }
                        }
                    }
                    $transforms[$key][TransformType::FIELDS] = array_values($transforms[$key][TransformType::FIELDS]);
                }

                //ADD CALCULATED FIELD
                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CALCULATED_FIELD) === 0) {
                    foreach ($transform[TransformType::FIELDS] as $k => $field) {
                        foreach ($delFields as $deletedField) {
                            if (strcmp($field[TransformType::FIELD], $deletedField) === 0) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }

                            if (strpos($field[TransformType::EXPRESSION], "row['" . $deletedField . "']") !== false) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }
                        }
                    }
                    $transforms[$key][TransformType::FIELDS] = array_values($transforms[$key][TransformType::FIELDS]);
                }

                //ADD CONCATENATION FIELD
                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CONCATENATED_FIELD) === 0) {
                    foreach ($transform[TransformType::FIELDS] as $k => $field) {
                        foreach ($delFields as $deletedField) {
                            if (strcmp($field[TransformType::FIELD], $deletedField) === 0) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }

                            if (strpos($field[TransformType::EXPRESSION], "row['" . $deletedField . "']") !== false) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }
                        }
                    }
                    $transforms[$key][TransformType::FIELDS] = array_values($transforms[$key][TransformType::FIELDS]);
                }

                //COMPARISON PERCENT
                if (strcmp($transform[TransformType::TYPE], TransformType::COMPARISON_PERCENT) === 0) {
                    foreach ($transform[TransformType::FIELDS] as $k => $field) {
                        foreach ($delFields as $deletedField) {
                            if (strcmp($field[TransformType::FIELD], $deletedField) === 0 || strcmp($field[TransformType::NUMERATOR], $deletedField) === 0 || strcmp($field[TransformType::DENOMINATOR], $deletedField) === 0) {
                                unset($transforms[$key][TransformType::FIELDS][$k]);
                            }
                        }
                    }
                    $transforms[$key][TransformType::FIELDS] = array_values($transforms[$key][TransformType::FIELDS]);
                }

                if (count($transforms[$key][TransformType::FIELDS]) === 0) {
                    unset($transforms[$key]);
                }
            }
        }

        $connectedDataSource->setMapFields($mapFields);
        $connectedDataSource->setRequires(array_values($requires));
        $connectedDataSource->setDuplicates(array_values($duplicates));
        $connectedDataSource->setFilters(array_values($filters));
        $connectedDataSource->setTransforms(array_values($transforms));
    }
}