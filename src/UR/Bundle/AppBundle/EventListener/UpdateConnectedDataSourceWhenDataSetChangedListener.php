<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
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
class UpdateConnectedDataSourceWhenDataSetChangedListener
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

    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $args->getEntity();

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $changedFields = $uow->getEntityChangeSet($dataSet);
        if (!array_key_exists('dimensions', $changedFields) && !array_key_exists('metrics', $changedFields)) {
            return;
        }

        /* detect changed metrics, dimensions */
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

        $updateFields = array_merge($updateDimensions, $updateMetrics);
        $deletedFields = array_merge($deletedDimensions, $deletedMetrics);

        /* update connected data sources */
        $connectedDataSources = $dataSet->getConnectedDataSources();

        foreach ($connectedDataSources as &$connectedDataSource) {
            $this->updateConfigForConnectedDataSource($connectedDataSource, $updateFields, $deletedFields, $dataSet->getId());
        }

        $dataSet->setConnectedDataSources($connectedDataSources);
        $em->merge($dataSet);

        /* update linked connected data sources */
        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        $linkedConnectedDataSources = $linkedMapDataSetRepository->getByMapDataSet($dataSet);

        /** @var LinkedMapDataSetInterface $linkedConnectedDataSource */
        foreach ($linkedConnectedDataSources as $linkedConnectedDataSource) {
            $augmentationConnectedDataSource = $linkedConnectedDataSource->getConnectedDataSource();
            $augmentationConnectedDataSource->setLinkedType(ConnectedDataSourceInterface::LINKED_TYPE_AUGMENTATION);
            $this->updateConfigForConnectedDataSource($linkedConnectedDataSource->getConnectedDataSource(), $updateFields, $deletedFields, $dataSet->getId());
        }
    }

    /**
     * delete Config For Connected DataSource
     *
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param array $updatedFields
     * @param array $deletedFields
     * @param $updatingDataSetId
     */
    private function updateConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, array $updatedFields, array $deletedFields, $updatingDataSetId = null)
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
                    $this->updateConnectedCollectionTransform($transform, $delFields, $updatedFields, $transforms, $key, $transformObject, $updatingDataSetId);
                }
            }
        }

        $connectedDataSource->setMapFields($mapFields);
        $connectedDataSource->setRequires(array_values($requires));
        $connectedDataSource->setTransforms(array_values($transforms));
    }

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

    private function updateConnectedCollectionTransform(array &$transform, array $delFields, array $updatedFields, array &$transforms, &$key, CollectionTransformerInterface $transformObject, $dataSetId = null)
    {
        if ($transformObject instanceof Augmentation) {
            if (array_key_exists($transformObject->getDestinationField(), $updatedFields)
                && $transformObject->getMapDataSet() == $dataSetId
            ) {
                $transform[Augmentation::MAP_CONDITION_KEY][Augmentation::MAP_DATA_SET_SIDE] = $updatedFields[$transformObject->getDestinationField()];
            }

            if (array_key_exists($transformObject->getSourceField(), $updatedFields)) {
                $transform[Augmentation::MAP_CONDITION_KEY][Augmentation::DATA_SOURCE_SIDE] = $updatedFields[$transformObject->getSourceField()];
            }

            if (in_array($transformObject->getDestinationField(), $delFields)
                && $transformObject->getMapDataSet() == $dataSetId
            ) {
                unset($transforms[$key]);
            }

            $mapFields = $transformObject->getMapFields();
            foreach ($mapFields as $index => $values) {
                if (in_array($values[Augmentation::DATA_SOURCE_SIDE], $delFields)) {
                    unset($mapFields[$index]);
                }

                if (in_array($values[Augmentation::MAP_DATA_SET_SIDE], $delFields)
                    && $transformObject->getMapDataSet() == $dataSetId
                ) {
                    unset($mapFields[$index]);
                }

                if (array_key_exists($values[Augmentation::DATA_SOURCE_SIDE], $updatedFields)) {
                    $values[Augmentation::DATA_SOURCE_SIDE] = $updatedFields[$values[Augmentation::DATA_SOURCE_SIDE]];
                    $mapFields[$index] = $values;
                }

                if (array_key_exists($values[Augmentation::MAP_DATA_SET_SIDE], $updatedFields)
                    && $transformObject->getMapDataSet() == $dataSetId
                ) {
                    $values[Augmentation::MAP_DATA_SET_SIDE] = $updatedFields[$values[Augmentation::MAP_DATA_SET_SIDE]];
                    $mapFields[$index] = $values;
                }
            }

            $transform[Augmentation::MAP_FIELDS_KEY] = $mapFields;

            $customConditions = $transformObject->getCustomCondition();
            foreach ($customConditions as $i => &$customCondition) {
                $field = $customCondition[Augmentation::CUSTOM_FIELD_KEY];
                if (in_array($field, $delFields)) {
                    unset($customConditions[$i]);
                }

                foreach ($updatedFields as $k => $v) {
                    if ($field == $k && $transformObject->getMapDataSet() == $dataSetId) {
                        $customCondition[Augmentation::CUSTOM_FIELD_KEY] = $v;
                    }
                }
            }

            $transform[Augmentation::CUSTOM_CONDITION] = $customConditions;
        } else if ($transformObject instanceof SubsetGroup) {
            $mapFields = $transformObject->getMapFields();
            foreach ($mapFields as $index => $values) {
                if (in_array($values[SubsetGroup::DATA_SOURCE_SIDE], $delFields)
                    || in_array($values[SubsetGroup::GROUP_DATA_SET_SIDE], $delFields)
                ) {
                    unset($mapFields[$index]);
                }

                if (array_key_exists($values[SubsetGroup::DATA_SOURCE_SIDE], $updatedFields)) {
                    $values[SubsetGroup::DATA_SOURCE_SIDE] = $updatedFields[$values[SubsetGroup::DATA_SOURCE_SIDE]];
                    $mapFields[$index] = $values;
                }

                if (array_key_exists($values[SubsetGroup::GROUP_DATA_SET_SIDE], $updatedFields)) {
                    $values[SubsetGroup::GROUP_DATA_SET_SIDE] = $updatedFields[$values[SubsetGroup::GROUP_DATA_SET_SIDE]];
                    $mapFields[$index] = $values;
                }
            }

            $groupFields = $transformObject->getGroupFields();
            foreach ($groupFields as &$field) {
                if (in_array($field, $delFields)) {
                    unset($field);
                    continue;
                }

                if (array_key_exists($field, $updatedFields)) {
                    $field = $updatedFields[$field];
                }
            }

            $transform[SubsetGroup::GROUP_FIELD_KEY] = $groupFields;
            $transform[SubsetGroup::MAP_FIELDS_KEY] = $mapFields;
        } else {

            $fieldKey = CollectionTransformerInterface::FIELD_KEY;
            $targetFieldKey = ExtractPattern::TARGET_FIELD_KEY;

//            if (in_array($transform[CollectionTransformerInterface::TYPE_KEY],
//                [CollectionTransformerInterface::EXTRACT_PATTERN, CollectionTransformerInterface::REPLACE_TEXT, CollectionTransformerInterface::CONVERT_CASE, CollectionTransformerInterface::NORMALIZE_TEXT]
//            )) {
//                $keyToCompare = ExtractPattern::TARGET_FIELD_KEY;
//            }

            foreach ($transform[CollectionTransformerInterface::FIELDS_KEY] as $k => &$field) {
                if (array_key_exists($fieldKey, $field)) {
                    if (in_array($field[$fieldKey], $delFields)) {
                        unset($transforms[$key][CollectionTransformerInterface::FIELDS_KEY][$k]);
                    }

                    if (array_key_exists($field[$fieldKey], $updatedFields)) {
                        $field[$fieldKey] = $updatedFields[$field[$fieldKey]];
                    }
                }

                if (array_key_exists($targetFieldKey, $field)) {
                    if (in_array($field[$targetFieldKey], $delFields)) {
                        unset($transforms[$key][CollectionTransformerInterface::FIELDS_KEY][$k]);
                    }

                    if (array_key_exists($field[$targetFieldKey], $updatedFields)) {
                        $field[$targetFieldKey] = $updatedFields[$field[$targetFieldKey]];
                    }
                }
            }

            $transforms[$key][CollectionTransformerInterface::FIELDS_KEY] = array_values($transforms[$key][CollectionTransformerInterface::FIELDS_KEY]);

            if (count($transforms[$key][CollectionTransformerInterface::FIELDS_KEY]) === 0) {
                unset($transforms[$key]);
            }
        }
    }
}