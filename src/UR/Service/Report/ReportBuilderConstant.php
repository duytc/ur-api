<?php


namespace UR\Service\Report;


class ReportBuilderConstant
{

    const DATA_SET_KEY = 'dataSets';
    const DATA_SET_VALUE = 'dataSet';
    const DIMENSIONS_DATA_SET_VALUE = 'dimensions';
    const METRICS_DATA_SET_VALUE = 'metrics';
    const FILTERS_KEY = 'filters';

    const TRANSFORMS_KEY = 'transform';
    const TRANSFORM_TARGET_KEY = 'transformType';
    const TRANSFORM_TYPE_KEY = 'type';
    const FIELD_NAME_KEY = 'field';

    const TYPE_NUMBER_DECIMAL_KEY = 'decimals';
    const TYPE_NUMBER_THOUSAND_SEPARATOR_KEY = 'thousandSeparator';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const DATE_FORMAT_FILTER_KEY = 'format';
    const START_DATE_FILTER_KEY = 'startDate';
    const END_DATE_FILTER_KEY = 'endDate';

    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    const DATE_FIELD_TYPE_FILTER_KEY = 'date';
    const NUMBER_FIELD_TYPE_FILTER_KEY = 'number';
    const TEXT_FIELD_TYPE_FILTER_KEY = 'text';

    const TARGET_TRANSFORMATION_KEY = 'transformType';
    const TYPE_TRANSFORMATION_KEY = 'type';
    const FIELD_NAME_TRANSFORMATION_KEY = 'field';
    const FROM_FORMAT_TRANSFORMATION_KEY = 'from';
    const TO_FORMAT_TRANSFORMATION_KEY = 'to';

    const THOUSAND_SEPARATOR_TRANSFORMATION_KEY = 'thousandsSeparator';
    const PREDICTION_TRANSFORMATION_KEY = 'decimals';
    const SCALE_TRANSFORMATION_KEY = 'scale';

    const TARGET_TRANSFORMATION_SINGLED_VALUE = 'single-field';
    const TARGET_TRANSFORMATION_ALL_VALUE = 'all-fields';
    const GROUP_BY_TRANSFORMATION_VALUE = 'groupBy';
    const FIELDS_GROUP_BY_TRANSFORMATION_VALUE = 'fields';
    const SORT_BY_TRANSFORMATION_VALUE = 'sortBy';

    const DATE_FORMAT_TRANSFORMATION_VALUE = 'format';
    const NUMBER_FORMAT_TRANSFORMATION_VALUE = 'number';

    const JOIN_BY_KEY = 'joinBy';

} 