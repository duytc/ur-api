<?php

namespace UR\Bundle\ReportApiBundle\Behaviors;


use Doctrine\Common\Collections\Collection;
use SplDoublyLinkedList;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Formats\ColumnPositionFormat;
use UR\Domain\DTO\Report\Formats\DateFormat;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\Report\ReportViewFormatterInterface;
use UR\Service\Report\SqlBuilder;

trait DashBoardUtilTrait
{
    public static $COMPARISON_TYPE_DAY_OVER_DAY = 'day-over-day';
    public static $COMPARISON_TYPE_WEEK_OVER_WEEK = 'week-over-week';
    public static $COMPARISON_TYPE_MONTH_OVER_MONTH = 'month-over-month';
    public static $COMPARISON_TYPE_YEAR_OVER_YEAR = 'year-over-year';

    public static $ARRAY_DATE_AND_DATETIME_TYPE = ['date', 'datetime'];

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function validateReportViewForDashboard(ReportViewInterface $reportView)
    {
        /* Date in dimension */
        $fieldTypes = $reportView->getFieldTypes();
        $dimensions = $reportView->getDimensions();
        $dateFieldsFromDimensions = [];

        foreach ($dimensions as $dimension) {
            if (!array_key_exists($dimension, $fieldTypes) || !in_array($fieldTypes[$dimension], self::$ARRAY_DATE_AND_DATETIME_TYPE)) {
                continue;
            }

            $dateFieldsFromDimensions[] = $dimension;
        }

        if (empty($dateFieldsFromDimensions)) {
            throw new BadRequestHttpException('Expected The master report contains at least one Date field in dimension!');
        }

        /* If has join date: must set visible */
        $joinBy = $reportView->getJoinBy();
        foreach ($joinBy as $joinConfig) {
            if (!is_array($joinConfig)
                || !array_key_exists(SqlBuilder::JOIN_CONFIG_JOIN_FIELDS, $joinConfig)
                || !is_array($joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS])
                || !array_key_exists(SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD, $joinConfig)
                || !array_key_exists(SqlBuilder::JOIN_CONFIG_VISIBLE, $joinConfig)
            ) {
                continue;
            }

            $joinFields = $joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            $isVisible = $joinConfig[SqlBuilder::JOIN_CONFIG_VISIBLE];

            foreach ($joinFields as $joinField) {
                if (!is_array($joinField)
                    || !array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $joinField)
                    || !array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $joinField)
                ) {
                    continue;
                }

                // check if join on date fields or not
                $inputJoinField = $joinField[SqlBuilder::JOIN_CONFIG_FIELD] . '_' . $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
                if (!array_key_exists($inputJoinField, $fieldTypes) || !in_array($fieldTypes[$inputJoinField], self::$ARRAY_DATE_AND_DATETIME_TYPE)) {
                    continue;
                }

                // check if isVisible is set
                if (!$isVisible) {
                    throw new BadRequestHttpException('Expected The Date Join config has been checked visible!');
                }
            }
        }

        /*
         * Have date filter and enabled userProvided
         * filter format:
         * [
         *     {
         *         "field":"date",
         *         "type":"date",
         *         "format":null,
         *         "dateValue":{
         *             "startDate":"2015-03-20",
         *             "endDate":"2018-03-20"
         *         },
         *         "userProvided":true,
         *         "dateType":"customRange",
         *         "filterOld":true
         *     }
         * ]
         */
        $filters = [];

        /** @var ReportViewDataSetInterface[]|Collection $reportViewDataSets */
        $reportViewDataSets = $reportView->getReportViewDataSets();
        foreach ($reportViewDataSets as $reportViewDataSet) {
            $filtersForDataSet = $reportViewDataSet->getFilters();
            if (is_array($filtersForDataSet)) {
                $filters = array_merge($filters, $filtersForDataSet);
            }
        }

        if ($reportView->isSubView()) {
            $filtersForSubView = $reportView->getFilters();
            if (is_array($filtersForSubView)) {
                $filters = array_merge($filters, $filtersForSubView);
            }
        }

        $foundValidDateFilter = false;

        foreach ($filters as $filter) {
            if (is_array($filter)
                && array_key_exists(AbstractFilter::FILTER_TYPE_KEY, $filter)
                && $filter[AbstractFilter::FILTER_TYPE_KEY] === AbstractFilter::TYPE_DATE
                && array_key_exists(DateFilter::DATE_USER_PROVIDED_FILTER_KEY, $filter)
                && $filter[DateFilter::DATE_USER_PROVIDED_FILTER_KEY] // true to allow get report with custom date range
            ) {
                $foundValidDateFilter = true;
                break;
            }
        }

        if (!$foundValidDateFilter) {
            throw new BadRequestHttpException('Expected The master report contains at least one Date filter and enabled userProvided option!');
        }
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function validateReportViewToGetComparisonData(ReportViewInterface $reportView)
    {
        $this->validateReportViewForDashboard($reportView);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function validateReportViewToGetOverviewData(ReportViewInterface $reportView)
    {
        $this->validateReportViewForDashboard($reportView);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return string
     */
    public function getDateFieldFromReportViewForDashboard(ReportViewInterface $reportView)
    {
        /* get all date fields from report view dimensions */
        $fieldTypes = $reportView->getFieldTypes();
        $dimensions = $reportView->getDimensions();
        $dateFieldsFromDimensions = [];

        foreach ($dimensions as $dimension) {
            if (!in_array($dimension, $fieldTypes) || !in_array($fieldTypes[$dimension], self::$ARRAY_DATE_AND_DATETIME_TYPE)) {
                continue;
            }

            $dateFieldsFromDimensions[] = $dimension;
        }

        /* If single date => use */
        if (count($dateFieldsFromDimensions) === 1) {
            $dateField = $dateFieldsFromDimensions[0];
            $dateFormat = $this->getDateFormatFromReportViewFormats($dateField, $reportView);

            return [
                'field' => $dateField,
                'format' => $dateFormat
            ];
        }

        /* if multi date from filter */
        // if join on date: prefer joined date
        $allInputOutputDateFieldsFromJoinConfig = $this->getInputOutputDateFieldsInJoinByFromReportView($reportView);
        $allOutputDateFieldsFromJoinConfig = $allInputOutputDateFieldsFromJoinConfig['output'];
        if (!empty($allOutputDateFieldsFromJoinConfig)) {
            // refer first output join date field
            $dateField = $allOutputDateFieldsFromJoinConfig[0];
            $dateFormat = $this->getDateFormatFromReportViewFormats($dateField, $reportView);

            return [
                'field' => $dateField,
                'format' => $dateFormat
            ];
        }

        // if not join on date: prefer date in first filter
        $dateFieldsFromFilters = $this->getDateFieldsInFiltersFromReportView($reportView);
        if (!empty($dateFieldsFromFilters)) {
            $dateField = $dateFieldsFromFilters[0];
            $dateFormat = $this->getDateFormatFromReportViewFormats($dateField, $reportView);

            return [
                'field' => $dateField,
                'format' => $dateFormat
            ];
        }

        /* skip date field from transforms because they are metrics only */

        return false;
    }

    /**
     * @param string $dateField
     * @param ReportViewInterface $reportView
     * @return string
     */
    public function getDateFormatFromReportViewFormats($dateField, ReportViewInterface $reportView)
    {
        $dateFormat = 'YYYY-MM-DD';

        $dateFieldFormats = $this->getDateFieldFormatsFromReportView($reportView);
        if (empty($dateFieldFormats)) {
            // return default
            return $dateFormat;
        }

        foreach ($dateFieldFormats as $dateFieldFormat) {
            if (!is_array($dateFieldFormat) || !array_key_exists('field', $dateFieldFormat) || !array_key_exists('format', $dateFieldFormat)) {
                continue;
            }

            if ($dateFieldFormat['field'] === $dateField) {
                return $dateFieldFormat['format'];
            }
        }

        // return default
        return $dateFormat;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array format as
     * [
     *    'field' => $field,
     *    'phpFormat' => $phpFormat, // in php. e.g Y-m-D
     *    'format' => $format // in js. e.g YYYY-MM-DD
     * ]
     */
    public function getDateFieldFormatsFromReportView(ReportViewInterface $reportView)
    {
        $dateFieldFormats = [];

        $formats = $reportView->getFormats();
        if (!is_array($formats) || empty($formats)) {
            return $dateFieldFormats;
        }

        /*
         * "formats":[
         *     {
         *         "type":"currency",
         *         "fields":[
         *             "revenue_2"
         *         ],
         *         "currency":"$",
         *         "convertEmptyValueToZero":true
         *     },
         *     {
         *         "type":"date",
         *         "fields":[
         *             "date_2"
         *         ],
         *         "format":"d\/m\/Y"
         *     }
         * ],
         */

        foreach ($formats as $format) {
            if (!is_array($format)
                || !array_key_exists(FormatInterface::FORMAT_TYPE_KEY, $format)
                || $format[FormatInterface::FORMAT_TYPE_KEY] !== FormatInterface::FORMAT_TYPE_DATE
                || !array_key_exists(DateFormat::FIELDS_NAME_KEY, $format)
                || !is_array($format[DateFormat::FIELDS_NAME_KEY])
                || !array_key_exists(DateFormat::OUTPUT_FORMAT_KEY, $format)
            ) {
                continue;
            }

            $fields = $format[DateFormat::FIELDS_NAME_KEY];
            $dateFormat = $format[DateFormat::OUTPUT_FORMAT_KEY];

            foreach ($fields as $field) {
                $dateFieldFormats[] = [
                    'field' => $field,
                    'phpFormat' => $dateFormat, // in php. e.g Y-m-D
                    'format' => $this->convertPHPDateFormatToFullFormatForJS($dateFormat) // in js. e.g YYYY-MM-DD
                ];
            }
        }

        return $dateFieldFormats;
    }

    /**
     * convert PHP date format to full DateFormat for JS
     * e.g:
     * - Y m, d => YYYY MM, DD
     * - Y--m--D => YYYY--MM--DDD
     * - Y/M, d => YYYY/MMM, DD
     * - ...
     *
     * @param string $dateFormat
     * @return string|bool false if dateFormat is not a string
     */
    public static function convertPHPDateFormatToFullFormatForJS($dateFormat)
    {
        $supportedDateFormats = array_flip(\UR\Service\Parser\Transformer\Column\DateFormat::SUPPORTED_DATE_FORMATS);
        if (!array_key_exists($dateFormat, $supportedDateFormats)) {
            return false;
        }

        return $supportedDateFormats[$dateFormat];
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function getInputOutputDateFieldsInJoinByFromReportView(ReportViewInterface $reportView)
    {
        $allInputDateFieldsFromJoinConfig = [];
        $allOutputDateFieldsFromJoinConfig = [];
        $fieldTypes = $reportView->getFieldTypes();
        $joinBy = $reportView->getJoinBy();

        foreach ($joinBy as $joinConfig) {
            if (!is_array($joinConfig)
                || !array_key_exists(SqlBuilder::JOIN_CONFIG_JOIN_FIELDS, $joinConfig)
                || !is_array($joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS])
                || !array_key_exists(SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD, $joinConfig)
            ) {
                continue;
            }

            $joinFields = $joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            $hasInputJoinFields = false;

            foreach ($joinFields as $joinField) {
                if (!is_array($joinField)
                    || !array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $joinField)
                    || !array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $joinField)
                ) {
                    continue;
                }

                $inputJoinField = $joinField[SqlBuilder::JOIN_CONFIG_FIELD] . '_' . $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
                if (!array_key_exists($inputJoinField, $fieldTypes) || !in_array($fieldTypes[$inputJoinField], self::$ARRAY_DATE_AND_DATETIME_TYPE)) {
                    continue;
                }

                $allInputDateFieldsFromJoinConfig[] = $joinField[SqlBuilder::JOIN_CONFIG_FIELD] . '_' . $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
                $hasInputJoinFields = true;
            }

            // add output join field if has input join fields before
            if ($hasInputJoinFields) {
                $allOutputDateFieldsFromJoinConfig[] = $joinConfig[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD];
            }
        }

        return [
            'input' => $allInputDateFieldsFromJoinConfig,
            'output' => $allOutputDateFieldsFromJoinConfig,
        ];
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public static function getDateFieldsInFiltersFromReportView(ReportViewInterface $reportView)
    {
        /** @var ReportViewDataSetInterface[] $reportViewDataSets */
        $reportViewDataSets = $reportView->getReportViewDataSets();
        $dateFieldsFromFilters = [];

        foreach ($reportViewDataSets as $reportViewDataSet) {
            if (!is_array($reportViewDataSet->getFilters())) {
                continue;
            }

            foreach ($reportViewDataSet->getFilters() as $filter) {
                if (!is_array($filter)
                    || !array_key_exists(AbstractFilter::FILTER_TYPE_KEY, $filter)
                    || !in_array($filter[AbstractFilter::FILTER_TYPE_KEY], self::$ARRAY_DATE_AND_DATETIME_TYPE)
                    || !array_key_exists(AbstractFilter::FILTER_FIELD_KEY, $filter)
                ) {
                    continue;
                }

                $dateFieldsFromFilters[] = $filter[AbstractFilter::FILTER_FIELD_KEY] . '_' . $reportViewDataSet->getDataSet()->getId();
            }
        }

        // get all filters if is subView
        if ($reportView->isSubView()) {
            if (is_array($reportView->getFilters()) && count($reportView->getFilters()) > 0) {
                foreach ($reportView->getFilters() as $filter) {
                    if (!is_array($filter)
                        || !array_key_exists(AbstractFilter::FILTER_TYPE_KEY, $filter)
                        || !in_array($filter[AbstractFilter::FILTER_TYPE_KEY], self::$ARRAY_DATE_AND_DATETIME_TYPE)
                        || !array_key_exists(AbstractFilter::FILTER_FIELD_KEY, $filter)
                        || !array_key_exists(AbstractFilter::FILTER_DATA_SET_KEY, $filter)
                    ) {
                        continue;
                    }

                    $dateFieldsFromFilters[] = $filter[AbstractFilter::FILTER_FIELD_KEY] . '_' . $filter[AbstractFilter::FILTER_DATA_SET_KEY];
                }
            }
        }

        return $dateFieldsFromFilters;
    }

    /**
     * @param string $comparisonType
     * @return false|array format as
     * [
     *     'current': [
     *         'startDate': '',
     *         'endDate': ''
     *     ],
     *     'history': [
     *         'startDate': '',
     *         'endDate': ''
     *     ]
     * ]
     */
    public function getStartDateEndDateDueToComparisonType($comparisonType)
    {
        switch ($comparisonType) {
            case self::$COMPARISON_TYPE_DAY_OVER_DAY:
                return [
                    'current' => [
                        'startDate' => (new \DateTime('yesterday'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('yesterday'))->format('Y-m-d')
                    ],
                    'history' => [
                        'startDate' => (new \DateTime('-2 days'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('-2 days'))->format('Y-m-d')
                    ]
                ];

            case self::$COMPARISON_TYPE_WEEK_OVER_WEEK:
                return [
                    'current' => [
                        'startDate' => (new \DateTime('-7 days'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('yesterday'))->format('Y-m-d')
                    ],
                    'history' => [
                        'startDate' => (new \DateTime('-14 days'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('-8 days'))->format('Y-m-d')
                    ]
                ];

            case self::$COMPARISON_TYPE_MONTH_OVER_MONTH:
                return [
                    'current' => [
                        'startDate' => (new \DateTime('-30 days'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('yesterday'))->format('Y-m-d')
                    ],
                    'history' => [
                        'startDate' => (new \DateTime('-60 days'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('-31 days'))->format('Y-m-d')
                    ]
                ];

            case self::$COMPARISON_TYPE_YEAR_OVER_YEAR:
                return [
                    'current' => [
                        'startDate' => (new \DateTime('first day of January this year'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('yesterday'))->format('Y-m-d')
                    ],
                    'history' => [
                        'startDate' => (new \DateTime('first day of January last year'))->format('Y-m-d'),
                        'endDate' => (new \DateTime('last day of December last year'))->format('Y-m-d')
                    ]
                ];
        }

        return false;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return string|null
     */
    public function getDefaultSortFieldForReportView(ReportViewInterface $reportView)
    {
        $metrics = $reportView->getMetrics();
        $fieldTypes = $reportView->getFieldTypes();

        // get columns positions from format if has
        $columnPosition = $this->getColumnPositionFromReportViewFormats($reportView);

        // merge fields from column position with metrics, prefer fields from column position first
        $sortedMetrics = $columnPosition;
        foreach ($metrics as $metric) {
            if (in_array($metric, $sortedMetrics)) {
                // skip metric is already in columnPosition
                continue;
            }

            $sortedMetrics[] = $metric;
        }

        // get default sort field
        foreach ($sortedMetrics as $sortedMetric) {
            if (array_key_exists($sortedMetric, $fieldTypes) && ($fieldTypes[$sortedMetric] === FieldType::NUMBER || $fieldTypes[$sortedMetric] === FieldType::DECIMAL)) {
                // return the first numeric metric of report view
                return $sortedMetric;
            }
        }

        return null;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return string
     */
    public function getColumnPositionFromReportViewFormats(ReportViewInterface $reportView)
    {
        $formats = $reportView->getFormats();
        if (!is_array($formats) || empty($formats)) {
            // return default
            return [];
        }

        /*
         * "formats":[
         *     {
         *         "fields": ["requests_2", "impressions_2", "revenue_2", "pre add calc 1", "pre add calc 2"]
                   "type": "columnPosition"
         *     },
         * ],
         */

        foreach ($formats as $format) {
            if (!is_array($format)
                || !array_key_exists(FormatInterface::FORMAT_TYPE_KEY, $format)
                || $format[FormatInterface::FORMAT_TYPE_KEY] !== FormatInterface::FORMAT_TYPE_COLUMN_POSITION
                || !array_key_exists(ColumnPositionFormat::FIELDS_NAME_KEY, $format)
            ) {
                continue;
            }

            $fields = $format[ColumnPositionFormat::FIELDS_NAME_KEY];
            if (!is_array($fields) || empty($fields)) {
                continue;
            }

            // return first match
            return $fields;
        }

        // return default
        return [];
    }

    /**
     * @param array $reports
     * @return array
     */
    public function getMinimizeReportForComparison(array $reports)
    {
        $KEYS_FOR_COMPARISON_REPORTS = [
            ReportResult::REPORT_RESULT_COLUMNS,
            ReportResult::REPORT_RESULT_TOTAL,
            ReportResult::REPORT_RESULT_TYPES
        ];

        foreach ($reports as $k => $v) {
            if (!in_array($k, $KEYS_FOR_COMPARISON_REPORTS)) {
                unset($reports[$k]);
            }
        }

        return $reports;
    }

    /**
     * @param array $reports
     * @return array
     */
    public function getMinimizeReportForOverview(array $reports)
    {
        $KEYS_FOR_OVERVIEW_REPORTS = [
            ReportResult::REPORT_RESULT_COLUMNS,
            ReportResult::REPORT_RESULT_TOTAL,
            ReportResult::REPORT_RESULT_REPORTS,
            ReportResult::REPORT_RESULT_TYPES
        ];

        foreach ($reports as $k => $v) {
            if (!in_array($k, $KEYS_FOR_OVERVIEW_REPORTS)) {
                unset($reports[$k]);
            }
        }

        return $reports;
    }

    /**
     * @param array $reports
     * @param string $dateField
     * @param array|FormatInterface[] $formats
     * @param ReportViewFormatterInterface $reportViewFormatter
     * @param array $averageFields
     * @return array
     */
    public function groupReportsByDate(array $reports, $dateField, array $formats = [], ReportViewFormatterInterface $reportViewFormatter, $averageFields = [])
    {
        if (!array_key_exists(ReportResult::REPORT_RESULT_REPORTS, $reports)
            || !array_key_exists(ReportResult::REPORT_RESULT_TYPES, $reports)
        ) {
            return $reports;
        }

        $reportsDetail = $reports[ReportResult::REPORT_RESULT_REPORTS];
        if (!is_array($reportsDetail)) {
            return $reports;
        }

        /* get number/decimal fields in types */
        $fieldTypes = $reports[ReportResult::REPORT_RESULT_TYPES];
        if (!array_key_exists($dateField, $fieldTypes)
            || !in_array($fieldTypes[$dateField], [FieldType::DATE, FieldType::DATETIME])
        ) {
            return $reports;
        }

        $numericFieldTypes = array_filter($fieldTypes, function ($fieldType) {
            return in_array($fieldType, [FieldType::NUMBER, FieldType::DECIMAL]);
        });

        /*
         * do sum
         * $didSumReportsDetail format:
         * [
         *     date_1 => [...report detail 1...],
         *     date_2 => [...report detail 1...],
         *     ...
         * ]
         */
        $didSumReportsDetail = [];

        /*
         * [ date_1 => 10, date_2 => 20, ... ]
         */
        $sameDateCount = [];

        foreach ($reportsDetail as $reportDetail) {
            // skip not have date field
            if (!array_key_exists($dateField, $reportDetail)) {
                continue;
            }

            // count same date
            $dateValue = $reportDetail[$dateField];
            if (!array_key_exists($dateValue, $sameDateCount)) {
                $sameDateCount[$dateValue] = 0;
            }
            $sameDateCount[$dateValue] = $sameDateCount[$dateValue] + 1;

            foreach ($reportDetail as $fieldName => $value) {
                // skip if not dateField and not number fields
                if ($fieldName !== $dateField && !array_key_exists($fieldName, $numericFieldTypes)) {
                    continue;
                }

                // init sum for date
                if (!array_key_exists($dateValue, $didSumReportsDetail)) {
                    $didSumReportsDetail[$dateValue] = [];

                    // add date field value
                    $didSumReportsDetail[$dateValue][$dateField] = $dateValue;
                }

                // do sum
                if (array_key_exists($fieldName, $numericFieldTypes)) {
                    // init sum for number field
                    if (!array_key_exists($fieldName, $didSumReportsDetail[$dateValue])) {
                        $didSumReportsDetail[$dateValue][$fieldName] = 0;
                    }

                    // convert value of field formats to number, e.g currency, percentage, number, ... formats
                    // so, need restore the format before returning
                    $value = preg_replace('/[^0-9-.]/', '', $value);

                    $didSumReportsDetail[$dateValue][$fieldName] += $value;
                }

                // this line is reached if date field!
            }
        }

        /* Do average - Due to showInTotal format changed to support sum or average calculation */
        foreach ($didSumReportsDetail as $date => &$detail) {
            foreach ($detail as $field => &$value) {
                if (!in_array($field, $averageFields)) {
                    // keep sum
                    continue;
                }

                // do average
                $value = round($value / $sameDateCount[$date], 12);
            }

            unset($field, $value);
        }

        unset($date, $detail);

        $didSumReportsDetail = array_values($didSumReportsDetail);

        /* restore the output format if need */
        if (!empty($formats)) {
            // very important: skip date format for dateField because already formatted before
            // e.g date is already d/m/Y, if format again we will get null
            $formats = array_filter($formats, function ($format) {
                return (!$format instanceof DateFormat);
            });

            // temp create result obj
            gc_enable();
            $newReports = new SplDoublyLinkedList();
            foreach ($didSumReportsDetail as $report) {
                $newReports->push($report);
                unset($report);
            }

            unset($report);
            gc_collect_cycles();

            $reportResult = new ReportResult($newReports, $total = [], $average = [], null, $headers = [], $types = [], $totalReport = 0);

            // format
            $reportViewFormatter->formatReports($reportResult, $formats, $metrics = [], $dimensions = []);

            // get formatted reports
            $didSumReportsDetail = $reportResult->generateReports()->toArray()[ReportResult::REPORT_RESULT_REPORTS];
        }

        /* finalize result */
        $reports[ReportResult::REPORT_RESULT_REPORTS] = $didSumReportsDetail;

        return $reports;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function getAverageFieldsFromReportView(ReportViewInterface $reportView)
    {
        $showInTotal = $reportView->getShowInTotal();
        if (!is_array($showInTotal)) {
            return [];
        }

        $averageFields = [];

        foreach ($showInTotal as $config) {
            if (!is_array($config)
                || !array_key_exists('type', $config)
                || $config['type'] !== 'average'
                || !array_key_exists('fields', $config)
            ) {
                continue;
            }

            $averageFields = array_merge($averageFields, $config['fields']);
        }

        return $averageFields;
    }
}