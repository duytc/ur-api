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

    public function validateFilters(DataSetInterface $dataSet, $connDataSource)
    {

        /**@var ConnectedDataSourceInterface $connDataSource */
        if ($connDataSource->getFilters() !== null)
            foreach ($connDataSource->getFilters() as $fieldName => $value) {

                if (!array_key_exists($fieldName, $dataSet->getDimensions()) && !array_key_exists($fieldName, $dataSet->getMetrics())) {
                    return false;
                }

                if (!array_key_exists(FilterType::TYPE, $value)) {
                    return false;
                }

                if (!Type::isValidFilterType($value[FilterType::TYPE])) {
                    return false;
                }

                if ((strcmp($value[FilterType::TYPE], Type::DATE) === 0) && !FilterType::isValidFilterDateType($value)) {
                    return false;
                }

                if (strcmp($value[FilterType::TYPE], Type::NUMBER) === 0 && !FilterType::isValidFilterNumberType($value)) {
                    return false;
                }

                if (strcmp($value[FilterType::TYPE], Type::TEXT) === 0 && !FilterType::isValidFilterTextType($value)) {
                    return false;
                }

            }
        return true;
    }

    public function validateTransforms(ConnectedDataSourceInterface $connDataSource)
    {
        if ($connDataSource->getTransforms() !== null) {

            foreach ($connDataSource->getTransforms() as $transformType => $fields) {

                if (!Type::isValidTransformType($transformType)) {
                    return false;
                }

                if ((strcmp($transformType, Type::SINGLE_FIELD) === 0) && !$this->validateSingleFieldTransform($connDataSource, $fields)) {
                    return false;
                }

                if ((strcmp($transformType, Type::ALL_FIELD) === 0) && !$this->validateAllFieldsTransform($connDataSource, $fields)) {
                    return false;
                }

            }
        }
        return true;
    }

    public function validateSingleFieldTransform(ConnectedDataSourceInterface $connectedDataSource, $fields)
    {
        foreach ($fields as $fieldName => $formats) {

            if (!in_array($fieldName, $connectedDataSource->getMapFields())) {
                return false;
            }

            if (!TransformType::isValidSingleFieldTransformType($formats)) {
                return false;
            }

        }

        return true;
    }

    public function validateAllFieldsTransform(ConnectedDataSourceInterface $connectedDataSource, $allFields)
    {
        foreach ($allFields as $transType => $fieldName) {

            if (!TransformType::isValidAllFieldTransformType($transType)) {
                return false;
            }

            foreach ($fieldName as $key => $trans) {
                if (TransformType::isGroupOrSortType($transType)) {

                    if (!in_array($trans, $connectedDataSource->getMapFields())) {
                        return false;
                    }
                }

                if (TransformType::isAddingType($transType)) {

                    if (in_array($key, $connectedDataSource->getMapFields())) {
                        return false;
                    }
                }
                //todo validte addcalculate fields

            }
        }

        return true;
    }
}