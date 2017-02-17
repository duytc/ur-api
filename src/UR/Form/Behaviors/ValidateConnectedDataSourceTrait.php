<?php

namespace UR\Form\Behaviors;


use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Repository\Core\ConnectedDataSourceRepository;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSet\Type;
use UR\Service\Parser\Filter\DateFilter;
use UR\Service\Parser\Filter\NumberFilter;
use UR\Service\Parser\Filter\TextFilter;

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

    /**
     * validate Filters of a connectDataSource
     *
     * @param DataSetInterface $dataSet
     * @param ConnectedDataSourceInterface $connDataSource
     * @return int|string return 0 if success, or a string if an error occurred
     */
    public function validateFilters(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        $connDataSourceFilters = $connDataSource->getFilters();

        if ($connDataSourceFilters === null) {
            return 0; // no filter
        }

        if (!is_array($connDataSourceFilters)) {
            throw new Exception(sprintf("ConnectedDataSource Filters Setting should be an array"));
        }

        foreach ($connDataSourceFilters as $filters) {

            if (!is_array($filters)) {
                throw new Exception(sprintf("Each element Filter Setting should be an array"));
            }

            if ($filters[FilterType::TYPE] === Type::NUMBER) {
                $numberFilter = new NumberFilter($filters);
            } else if ($filters[FilterType::TYPE] === Type::TEXT) {
                $textFilter = new TextFilter($filters);
            } else if ($filters[FilterType::TYPE] === Type::DATE) {
                $filters[FilterType::FORMAT] = '!Y-m-d';
                $dateFilter = new DateFilter($filters);
            } else {
                throw new Exception(sprintf("filter Setting error: filter of type [%s] is not supported", $filters[FilterType::TYPE]));
            }
        }

        return 0;
    }

    public function validateTransforms(DataSetInterface $dataSet, ConnectedDataSourceInterface $connDataSource)
    {
        if ($connDataSource->getTransforms() !== null) {

            foreach ($connDataSource->getTransforms() as $transform) {

                if (!TransformType::isValidTransformType($transform[TransformType::TYPE])) {
                    return sprintf("Transform all fields setting error: transform type should be one of %s", implode(", ", TransformType::$transformTypes));
                }

                if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE])) {
                    $validDateOrNumberTransformResult = $this->validateDateOrNumberTransform($connDataSource, $transform);
                    if ($validDateOrNumberTransformResult !== 0) {
                        return $validDateOrNumberTransformResult;
                    }
                } else {
                    if ($this->validateAllFieldsTransform($connDataSource, $transform) !== 0) {
                        return $this->validateAllFieldsTransform($connDataSource, $transform);
                    }
                }
            }
        }

        $fields = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        foreach ($fields as $field => $type) {
            if (!in_array($field, $connDataSource->getMapFields())) {
                continue;
            }

            if (strcmp($type, Type::DATE) === 0) {
                $count = 0;
                foreach ($connDataSource->getTransforms() as $transform) {
                    if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE])) {
                        if (strcmp($transform[TransformType::FIELD], $field) === 0) {
                            $count++;
                        }
                    }
                }

                if ($count === 0) {
                    return "Date type mapped should have a single field transformation";
                }
            }
        }

        return 0;
    }

    public function validateDateOrNumberTransform(ConnectedDataSourceInterface $connectedDataSource, $transform)
    {
       return TransformType::isValidDateOrNumberTransform($transform);
    }

    public function validateAllFieldsTransform(ConnectedDataSourceInterface $connectedDataSource, $transform)
    {
        //GROUP BY
        if (count($transform) !== 3 || !array_key_exists(TransformType::FIELDS, $transform)) {
            return "Transform setting error: Transform [" . $transform[TransformType::TYPE] . "] missing config information";
        }

        //SORT BY

        //COMPARISON PERCENT
        if (strcmp($transform[TransformType::TYPE], TransformType::COMPARISON_PERCENT) === 0) {
            foreach ($transform[TransformType::FIELDS] as $field) {

                if (!array_key_exists(TransformType::FIELD, $field) || !array_key_exists(TransformType::NUMERATOR, $field) || !array_key_exists(TransformType::DENOMINATOR, $field)) {
                    return "Transform ( " . $transform[TransformType::TYPE] . ") setting error: wrong configuration ";
                }

                if (in_array($field[TransformType::FIELD], $connectedDataSource->getMapFields())) {
                    return "Transform ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] should not be mapped ";
                }

                if (!$this->isDataSetFields($field[TransformType::FIELD], $connectedDataSource->getDataSet())) {
                    return "Transform ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] does not exist in dimensions or metrics ";
                }
            }
        }

        //ADD FIELDS
        if (strcmp($transform[TransformType::TYPE], TransformType::ADD_FIELD) === 0) {
            foreach ($transform[TransformType::FIELDS] as $field) {
                if (in_array($field[TransformType::FIELD], $connectedDataSource->getMapFields())) {
                    return "Transform ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] should not be mapped ";
                }

                if (!$this->isDataSetFields($field[TransformType::FIELD], $connectedDataSource->getDataSet())) {
                    return "Transform all fields ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] does not exist in dimensions or metrics ";
                }
            }
        }

        //ADD CALCULATED FIELDS
        if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CALCULATED_FIELD) === 0) {
            foreach ($transform[TransformType::FIELDS] as $field) {
                if (in_array($field[TransformType::FIELD], $connectedDataSource->getMapFields())) {
                    return "Transform all fields ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] should not be mapped ";
                }

                if (!$this->isDataSetFields($field[TransformType::FIELD], $connectedDataSource->getDataSet())) {
                    return "Transform all fields ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] does not exist in dimensions or metrics ";
                }
            }
        }

        //ADD CONCATENATION FIELDS
        if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CONCATENATED_FIELD) === 0) {
            foreach ($transform[TransformType::FIELDS] as $field) {
                if (in_array($field[TransformType::FIELD], $connectedDataSource->getMapFields())) {
                    return "Transform all fields ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] should not be mapped ";
                }

                if (!$this->isDataSetFields($field[TransformType::FIELD], $connectedDataSource->getDataSet())) {
                    return "Transform all fields ( " . $transform[TransformType::TYPE] . ") setting error: field [" . $field[TransformType::FIELD] . "] does not exist in dimensions or metrics ";
                }
            }
        }

        return 0;
    }

    public function validateAlertSetting(ConnectedDataSourceInterface $connDataSource)
    {
        if ($connDataSource->getAlertSetting() !== null) {
            if (array_diff(array_intersect(ConnectedDataSourceRepository::$alertSetting, $connDataSource->getAlertSetting()), $connDataSource->getAlertSetting())) {
                return false;
            }
        }

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