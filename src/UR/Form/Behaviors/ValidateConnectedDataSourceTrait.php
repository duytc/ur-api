<?php

namespace UR\Form\Behaviors;


use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

trait ValidateConnectedDataSourceTrait
{
    static $COMPARISON_NUMBER_VALUES = [
        'smaller',
        'smaller or equal',
        'equal',
        'not equal',
        'greater',
        'greater or equal',
        'in',
        'not'
    ];

    static $COMPARISON_TEXT_VALUES = [
        'contains',
        'not contains',
        'start with',
        'end with',
        'in',
        'not'
    ];

    static $FILTERS_TYPE_VALUES = [
        'date',
        'number',
        'text'
    ];

    static $TRANSFORMS_TYPE_VALUES = [
        'single-field',
        'all-fields'
    ];

    static $ALL_FIELDS_TRANSFORMS_TYPE_VALUES = [
        'groupBy',
        'sortBy',
        'addField',
        'addCalculatedField',
        'comparisonPercent'
    ];

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

                if (!in_array($value['type'], self::$FILTERS_TYPE_VALUES, true)) {
                    return false;
                }

                if ((strcmp($value['type'], "date") === 0) && !$this->validateFilterDateType($value)) {
                    return false;
                }

                if (strcmp($value['type'], "number") === 0 && !$this->validateFilterNumberType($value)) {
                    return false;
                }

                if (strcmp($value['type'], "text") === 0 && !$this->validateFilterTextType($value)) {
                    return false;
                }

            }
        return true;
    }

    public function validateTransforms(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        if ($connDataSource->getTransforms() !== null) {

            foreach ($connDataSource->getTransforms() as $transformType => $fields) {

                if (!in_array($transformType, self::$TRANSFORMS_TYPE_VALUES, true)) {
                    return false;
                }

                if ((strcmp($transformType, "single-field") === 0) && !$this->validateSingleFieldTransform($connDataSource, $fields)) {
                    return false;
                }

                if ((strcmp($transformType, "all-fields") === 0) && !$this->validateAllFieldsTransform($connDataSource, $fields)) {
                    return false;
                }

            }
        }
        return true;
    }

    public function validateFilterDateType($value)
    {
        if (count($value) !== 3 || !array_key_exists("from", $value) || !array_key_exists("to", $value)) {
            return false;
        }

        return true;
    }

    public function validateFilterNumberType($value)
    {
        if (count($value) !== 3 || !array_key_exists("comparison", $value) || !array_key_exists("compareValue", $value)) {
            return false;
        }

        if (!in_array($value['comparison'], self::$COMPARISON_NUMBER_VALUES, true)) {
            return false;
        }

        return true;
    }

    public function validateFilterTextType($value)
    {
        if (count($value) !== 3 || !array_key_exists("comparison", $value) || !array_key_exists("compareValue", $value)) {
            return false;
        }

        if (!in_array($value['comparison'], self::$COMPARISON_TEXT_VALUES, true)) {
            return false;
        }

        return true;
    }

    public function validateSingleFieldTransform(ConnectedDataSourceInterface $connectedDataSource, $fields)
    {
        foreach ($fields as $fieldName => $formats) {

            if (!in_array($fieldName, $connectedDataSource->getMapFields())) {
                return false;
            }

        }

        return true;
    }

    public function validateAllFieldsTransform(ConnectedDataSourceInterface $connectedDataSource, $allFields)
    {
        foreach ($allFields as $transType => $fieldName) {

            if (!in_array($transType, self::$ALL_FIELDS_TRANSFORMS_TYPE_VALUES)) {
                return false;
            }

            foreach ($fieldName as $trans) {

                if (!array_key_exists($trans, $connectedDataSource->getMapFields())) {
                    return false;
                }

            }
        }

        return true;
    }
}