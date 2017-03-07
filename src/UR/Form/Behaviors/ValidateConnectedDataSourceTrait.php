<?php

namespace UR\Form\Behaviors;


use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Repository\Core\ConnectedDataSourceRepository;
use UR\Service\DataSet\MetadataField;
use UR\Service\DataSet\FieldType;
use UR\Service\Parser\Filter\FilterFactory;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\TransformerFactory;

trait ValidateConnectedDataSourceTrait
{
    public function validateMappingFields(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        foreach ($connDataSource->getMapFields() as $mapField) {
            if (!array_key_exists($mapField, $dataSet->getDimensions()) && !array_key_exists($mapField, $dataSet->getMetrics())) {
                return false;
            }
        }

        return true;
    }

    public function validateRequireFields(ConnectedDataSourceInterface $connDataSource)
    {
        $allowedFields = array_merge($connDataSource->getMapFields(), array_flip(MetadataField::$internalFields));

        foreach ($connDataSource->getRequires() as $require) {
            if (!in_array($require, $allowedFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * validate Filters of a connectDataSource
     *
     * @param array $connDataSourceFilters
     */
    public function validateFilters($connDataSourceFilters)
    {
        if (!is_array($connDataSourceFilters) && $connDataSourceFilters !== null) {
            throw new Exception(sprintf("ConnectedDataSource Filters Setting should be an array"));
        }

        $filterFactory = new FilterFactory();
        foreach ($connDataSourceFilters as $filters) {
            $filterObject = $filterFactory->getFilter($filters);
            if ($filterObject !== null) {
                $filterObject->validate();
            }
        }
    }

    public function validateTransforms(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        $transformFactory = new TransformerFactory();

        foreach ($connDataSource->getTransforms() as $transform) {
            $transformObjects = $transformFactory->getTransform($transform);

            if (!is_array($transformObjects)) {
                $transformObjects->validate();
                continue;
            }

            foreach ($transformObjects as $transformObject) {
                $transformObject->validate();
            }
        }

        $fields = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        foreach ($fields as $field => $type) {
            if (!in_array($field, $connDataSource->getMapFields())) {
                continue;
            }

            if (strcmp($type, FieldType::DATE) === 0) {
                $count = 0;
                foreach ($connDataSource->getTransforms() as $transform) {
                    $transformObj = $transformFactory->getTransform($transform);

                    if ($transformObj instanceof DateFormat) {
                        if (strcmp($transformObj->getField(), $field) === 0) {
                            $count++;
                        }
                    }
                }

                if ($count === 0) {
                    throw  new Exception("Date type mapped should have a single field transformation");
                }
            }
        }
    }

    public function validateAlertSetting(ConnectedDataSourceInterface $connDataSource)
    {
        return true;
    }

    public function isDataSetFields($field, DataSetInterface $dataSet)
    {
        $dataSetFields = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        if (!array_key_exists($field, $dataSetFields)) {
            return false;
        }
        return true;
    }
}