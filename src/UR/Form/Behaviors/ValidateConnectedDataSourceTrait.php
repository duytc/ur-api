<?php

namespace UR\Form\Behaviors;


use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSet\Type;

trait ValidateConnectedDataSourceTrait
{
    public function validateMappingFields(DataSetInterface $dataSet, $connDataSource)
    {
        /**@var ConnectedDataSourceInterface $connDataSource */
        foreach ($connDataSource->getMapFields() as $mapField) {
            if (!array_key_exists($mapField, $dataSet->getDimensions()) && !array_key_exists($mapField, $dataSet->getMetrics())) {
                return false;
            }
        }

        return true;
    }

    public function validateRequireFields(DataSetInterface $dataSet, $connDataSource)
    {

        /**@var ConnectedDataSourceInterface $connDataSource */
        foreach ($connDataSource->getRequires() as $require) {
            if (!in_array($require, $connDataSource->getMapFields())) {
                return false;
            }
        }

        return true;
    }

    public function validateFilters(DataSetInterface $dataSet, $connDataSource)
    {

        /**@var ConnectedDataSourceInterface $connDataSource */
        if ($connDataSource->getFilters() !== null)
            foreach ($connDataSource->getFilters() as $filters) {

                if (!array_key_exists($filters[FilterType::FIELD], $dataSet->getDimensions()) && !array_key_exists($filters[FilterType::FIELD], $dataSet->getMetrics())) {
                    return false;
                }


                if (!array_key_exists(FilterType::TYPE, $filters)) {
                    return false;
                }

                if (!Type::isValidFilterType($filters[FilterType::TYPE])) {
                    return false;
                }

                if ((strcmp($filters[FilterType::TYPE], Type::DATE) === 0) && !FilterType::isValidFilterDateType($filters)) {
                    return false;
                }

                if (strcmp($filters[FilterType::TYPE], Type::NUMBER) === 0 && !FilterType::isValidFilterNumberType($filters)) {
                    return false;
                }

                if (strcmp($filters[FilterType::TYPE], Type::TEXT) === 0 && !FilterType::isValidFilterTextType($filters)) {
                    return false;
                }

            }
        return true;
    }

    public function validateTransforms(ConnectedDataSourceInterface $connDataSource)
    {
        if ($connDataSource->getTransforms() !== null) {

            foreach ($connDataSource->getTransforms() as $transform) {

                if (!Type::isValidTransformType($transform[TransformType::TRANSFORM_TYPE])) {
                    return false;
                }

                if ((strcmp($transform[TransformType::TRANSFORM_TYPE], Type::SINGLE_FIELD) === 0) && !$this->validateSingleFieldTransform($connDataSource, $transform)) {
                    return false;
                }

                if ((strcmp($transform[TransformType::TRANSFORM_TYPE], Type::ALL_FIELD) === 0) && !$this->validateAllFieldsTransform($connDataSource, $transform)) {
                    return false;
                }

            }
        }
        return true;
    }

    public function validateSingleFieldTransform(ConnectedDataSourceInterface $connectedDataSource, $transform)
    {
//        foreach ($fields as $fieldName => $formats) {

        if (!in_array($transform[TransformType::FIELD], $connectedDataSource->getMapFields())) {
            return false;
        }

        if (!TransformType::isValidSingleFieldTransformType($transform)) {
            return false;
        }

//        }

        return true;
    }

    public function validateAllFieldsTransform(ConnectedDataSourceInterface $connectedDataSource, $transform)
    {

        foreach ($transform as $k => $v) {

            if (!TransformType::isValidAllFieldTransformType($k)) {
                return false;
            }

            if (TransformType::isGroupOrSortType($k)) {
                foreach ($v as $field) {
                    if (!in_array($field, $connectedDataSource->getMapFields())) {
                        return false;
                    }
                }
            }

            if (TransformType::isAddingType($k)) {
                foreach ($v as $field) {
                    if (in_array($field[TransformType::FIELD], $connectedDataSource->getMapFields())) {
                        return false;
                    }
                }
            }

        }

        return true;
    }
}