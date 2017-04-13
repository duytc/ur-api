<?php

namespace UR\Service\Parser\Filter;


use Exception;
use UR\Service\DataSet\FieldType;

class FilterFactory
{
    /**
     * @param array $jsonFilter |null
     * @return null|DateFilter|NumberFilter|TextFilter
     * @throws \Exception
     */
    public function getFilter(array $jsonFilter)
    {
        if (!is_array($jsonFilter)) {
            throw new Exception(sprintf("Each element Filter Setting should be an array"));
        }

        /* all filter must has 'field' and 'name' key */
        if (!array_key_exists(ColumnFilterInterface::FIELD_TYPE_FILTER_KEY, $jsonFilter)
            || !array_key_exists(ColumnFilterInterface::FIELD_NAME_FILTER_KEY, $jsonFilter)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s" or "%s" does not exits in filter',
                ColumnFilterInterface::FIELD_TYPE_FILTER_KEY,
                ColumnFilterInterface::FIELD_NAME_FILTER_KEY));
        }

        /*
         * return type of filter base on TYPE KEY
         */
        $filterObject = null;
        switch ($jsonFilter[ColumnFilterInterface::FIELD_TYPE_FILTER_KEY]) {
            case ColumnFilterInterface::NUMBER:
                $filterObject = $this->getNumberFilter($jsonFilter);
                break;
            case ColumnFilterInterface::TEXT:
                $filterObject = $this->getTextFilter($jsonFilter);
                break;
            case ColumnFilterInterface::DATE:
                $filterObject = $this->getDateFilter($jsonFilter);
                break;
            default:
                throw new \Exception (sprintf('Filter type must be one of "%s", "%s" or "%s", "%s" given',
                    FieldType::NUMBER,
                    FieldType::TEXT,
                    FieldType::DATE,
                    $jsonFilter[ColumnFilterInterface::FIELD_TYPE_FILTER_KEY]));
        }

        return $filterObject;
    }

    private function getNumberFilter($filter)
    {
        if (!array_key_exists(NumberFilter::COMPARISON_TYPE_FILTER_KEY, $filter)
            || !array_key_exists(NumberFilter::COMPARISON_VALUE_FILTER_KEY, $filter)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s" or "%s" does not exits in number filter',
                NumberFilter::COMPARISON_TYPE_FILTER_KEY,
                NumberFilter::COMPARISON_VALUE_FILTER_KEY));
        }

        return new NumberFilter(
            $filter[NumberFilter::FIELD_NAME_FILTER_KEY],
            $filter[NumberFilter::COMPARISON_TYPE_FILTER_KEY],
            $filter[NumberFilter::COMPARISON_VALUE_FILTER_KEY]
        );
    }

    private function getTextFilter($filter)
    {
        if (!array_key_exists(TextFilter::COMPARISON_TYPE_FILTER_KEY, $filter)
            || !array_key_exists(TextFilter::COMPARISON_VALUE_FILTER_KEY, $filter)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s" or "%s" does not exits in text filter',
                TextFilter::COMPARISON_TYPE_FILTER_KEY,
                TextFilter::COMPARISON_VALUE_FILTER_KEY));
        }

        return new TextFilter(
            $filter[TextFilter::FIELD_NAME_FILTER_KEY],
            $filter[TextFilter::COMPARISON_TYPE_FILTER_KEY],
            $filter[TextFilter::COMPARISON_VALUE_FILTER_KEY]
        );
    }

    private function getDateFilter($filter)
    {
        if (!array_key_exists(DateFilter::FORMAT_FILTER_KEY, $filter)
            || !array_key_exists(DateFilter::START_DATE_FILTER_KEY, $filter)
            || !array_key_exists(DateFilter::END_DATE_FILTER_KEY, $filter)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s", "%s" or "%s" does not exits in date filter',
                DateFilter::FORMAT_FILTER_KEY,
                DateFilter::START_DATE_FILTER_KEY,
                DateFilter::END_DATE_FILTER_KEY));
        }

        return new DateFilter(
            $filter[DateFilter::FIELD_NAME_FILTER_KEY],
            $filter[DateFilter::START_DATE_FILTER_KEY],
            $filter[DateFilter::END_DATE_FILTER_KEY],
            $filter[DateFilter::FORMAT_FILTER_KEY]
        );
    }
}