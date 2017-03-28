<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Service\Parser\Transformer\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\TransformerFactory;
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

        $newFields = array_merge($newDimensions, $newMetrics);
        $updateFields = array_merge($updateDimensions, $updateMetrics);
        $deletedFields = array_merge($deletedDimensions, $deletedMetrics);

        // alter data_import table
        $this->workerManager->alterDataSetTable($entity->getId(), $newFields, $updateFields, $deletedFields);
        $this->updateFields = $updateFields;
        $this->deletedFields = $deletedFields;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        // detect all changed fields
        foreach ($this->changedEntities as $entity) {
            if (!$entity instanceof DataSetInterface) {
                continue;
            }
            // delete all configs of connected dataSources related to deletedFields
            $connectedDataSources = $entity->getConnectedDataSources();

            foreach ($connectedDataSources as &$connectedDataSource) {
                $this->updateConfigForConnectedDataSource($connectedDataSource, $this->updateFields, $this->deletedFields);
            }

            $entity->setConnectedDataSources($connectedDataSources);
            $em->merge($entity);

            $linkedConnectedDataSources = $linkedMapDataSetRepository->getByMapDataSet($entity);

            /** @var LinkedMapDataSetInterface $linkedConnectedDataSource */
            foreach($linkedConnectedDataSources as $linkedConnectedDataSource) {
                $this->updateConfigForConnectedDataSource($linkedConnectedDataSource->getConnectedDataSource(), $this->updateFields, $this->deletedFields);
            }
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
    private function updateConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, array $updatedFields, array $deletedFields)
    {
        $mapFields = $connectedDataSource->getMapFields();
        $requires = $connectedDataSource->getRequires();
        $transforms = $connectedDataSource->getTransforms();

        $delFields = [];
        foreach ($deletedFields as $deletedField => $type) {
            $delFields[] = $deletedField;
        }
        $mapFields = array_diff($mapFields, $delFields);

        foreach ($mapFields as &$mapField) {
            if (array_key_exists($mapField, $updatedFields)) {
                $mapField = $updatedFields[$mapField];
            }
        }

        $requires = array_values(array_diff($requires, $delFields));

        $transformerFactory = new TransformerFactory();
        foreach ($transforms as $key => &$transform) {
            $transformObjects = $transformerFactory->getTransform($transform);

            if ($transformObjects instanceof ColumnTransformerInterface) {
                if (in_array($transformObjects->getField(), $deletedFields)) {
                    unset($transforms[$key]);
                }

                if (array_key_exists($transformObjects->getField(), $updatedFields)) {
                    $transform[ColumnTransformerInterface::FIELD_KEY] = $updatedFields[$transformObjects->getField()];
                }

                continue;

            } else {

                if ($transformObjects instanceof GroupByColumns || $transformObjects instanceof SortByColumns) {
                    continue;
                }

                foreach ($transformObjects as $transformObject) {
                    $this->updateConnectedCollectionTransform($transform, $delFields, $updatedFields, $transforms, $key, $transformObject);
                }
            }
        }

        $connectedDataSource->setMapFields($mapFields);
        $connectedDataSource->setRequires(array_values($requires));
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
                $updateDimensions[$oldFieldName] = $newFieldName;
                unset($deletedFields[$oldFieldName]);
            }
        }

        $newDimensions = array_diff_key($values[1], $values[0]);
        foreach ($updateDimensions as $updateDimension) {
            if (array_key_exists($updateDimension, $newDimensions)) {
                unset($newDimensions[$updateDimension]);
            }
        }
    }

    public function updateConnectedCollectionTransform(array &$transform, array $delFields, array $updatedFields, array &$transforms, &$key, CollectionTransformerInterface $transformObject)
    {
        if ($transformObject instanceof Augmentation) {
            if (in_array($transformObject->getSourceField(), $delFields)) {
                unset($transforms[$key]);
            } else {
                $mapFields = $transformObject->getMapFields();
                foreach ($mapFields as $index => $values) {
                    if (in_array($values[Augmentation::DATA_SOURCE_SIDE], $delFields)) {
                        unset($mapFields[$index]);
                    } else if (array_key_exists($values[Augmentation::DATA_SOURCE_SIDE], $updatedFields)) {
                        $values[Augmentation::DATA_SOURCE_SIDE] = $updatedFields[$values[Augmentation::DATA_SOURCE_SIDE]];
                        $mapFields[$index] = $values;
                    }
                }

                $transform[Augmentation::MAP_FIELDS_KEY] = $mapFields;
            }
            $customConditions = $transformObject->getCustomCondition();
            foreach($customConditions as $i=>&$customCondition) {
                $field = $customCondition[Augmentation::CUSTOM_FIELD_KEY];
                if (in_array($field, $delFields)) {
                    unset($customConditions[$i]);
                }

                foreach($updatedFields as $k=>$v) {
                    if ($field == $k) {
                        $customCondition[Augmentation::CUSTOM_FIELD_KEY] = $v;
                    }
                }
            }

            $transform[Augmentation::CUSTOM_CONDITION] = $customConditions;
        } else {

            $keyToCompare = CollectionTransformerInterface::FIELD_KEY;

            if (in_array($transform[CollectionTransformerInterface::TYPE_KEY], [CollectionTransformerInterface::EXTRACT_PATTERN, CollectionTransformerInterface::REPLACE_TEXT])) {
                $keyToCompare = ExtractPattern::TARGET_FIELD_KEY;
            }

            foreach ($transform[CollectionTransformerInterface::FIELDS_KEY] as $k => &$field) {

                if (in_array($field[$keyToCompare], $delFields)) {
                    unset($transforms[$key][CollectionTransformerInterface::FIELDS_KEY][$k]);
                }

                if (array_key_exists($field[$keyToCompare], $updatedFields)) {
                    $field[$keyToCompare] = $updatedFields[$field[$keyToCompare]];
                }
            }

            $transforms[$key][CollectionTransformerInterface::FIELDS_KEY] = array_values($transforms[$key][CollectionTransformerInterface::FIELDS_KEY]);

            if (count($transforms[$key][CollectionTransformerInterface::FIELDS_KEY]) === 0) {
                unset($transforms[$key]);
            }
        }
    }
}