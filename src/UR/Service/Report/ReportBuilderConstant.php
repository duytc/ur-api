<?php


namespace UR\Service\Report;


class ReportBuilderConstant
{

    const DATA_SET_KEY = 'dataSets';
    const DATA_SET_VALUE = 'dataSet';
    const DIMENSIONS_DATA_SET_VALUE = 'dimensions';
    const METRICS_DATA_SET_VALUE = 'metrics';

    const FILTERS_KEY = 'filters';
    const TRANSFORMS_KEY = 'transforms';
    const TRANSFORM_TARGET_KEY = 'transformType';
    const TRANSFORM_TYPE_KEY = 'type';
    const FIELD_NAME_KEY = 'field';

    const TYPE_NUMBER_DECIMAL_KEY = 'decimals';
    const TYPE_NUMBER_THOUSAND_SEPARATOR_KEY = 'thousandSeparator';

    const FIELD_TYPE_FILTER_KEY = 'fieldType';
    const FILED_NAME_FILTER_KEY = 'fieldName';
    const DATE_FORMAT_FILTER_KEY = 'dateFormat';
    const DATE_RANGE_FILTER_KEY = 'dateRange';

    const COMPARISON_TYPE_FILTER_KEY = 'comparisonType';
    const COMPARISON_VALUE_FILTER_KEY = 'comparisonValue';

    const DATE_FIELD_TYPE_FILTER_KEY = 'date';
    const NUMBER_FIELD_TYPE_FILTER_KEY = 'number';
    const TEXT_FIELD_TYPE_FILTER_KEY = 'text';

    const TARGET_TRANSFORMATION_KEY = 'target';
    const TYPE_TRANSFORMATION_KEY = 'type';
    const FIELD_NAME_TRANSFORMATION_KEY = 'fieldName';
    const FROM_FORMAT_TRANSFORMATION_KEY = 'fromFormat';
    const TO_FORMAT_TRANSFORMATION_KEY = 'toFormat';

    const THOUSAND_SEPARATOR_TRANSFORMATION_KEY = 'thousandSeparator';
    const PREDICTION_TRANSFORMATION_KEY = 'prediction';
    const SCALE_TRANSFORMATION_KEY = 'scale';

    const TARGET_TRANSFORMATION_SINGLED_VALUE = 'single';
    const TARGET_TRANSFORMATION_ALL_VALUE = 'all';

    const DATE_FORMAT_TRANSFORMATION_VALUE = 'dateFormat';
    const NUMBER_FORMAT_TRANSFORMATION_VALUE = 'numberFormat';

    const JOIN_BY_KEY = 'joinByFields';

} 