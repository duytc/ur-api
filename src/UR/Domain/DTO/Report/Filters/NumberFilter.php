<?php

namespace UR\Domain\DTO\Report\Filters;

use UR\Service\DTO\Report\ReportResult;

class NumberFilter extends AbstractFilter implements NumberFilterInterface
{
    const COMPARISON_TYPE_EQUAL = 'equal';
    const COMPARISON_TYPE_SMALLER = 'smaller';
    const COMPARISON_TYPE_SMALLER_OR_EQUAL = 'smaller or equal';
    const COMPARISON_TYPE_GREATER = 'greater';
    const COMPARISON_TYPE_GREATER_OR_EQUAL = 'greater or equal';
    const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
    const COMPARISON_TYPE_IN = 'in';
    const COMPARISON_TYPE_NOT_IN = 'not in';
    const COMPARISON_TYPE_NULL = 'isEmpty';
    const COMPARISON_TYPE_NOT_NULL = 'isNotEmpty';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    const EPSILON = 10e-12;

    public static $SUPPORTED_COMPARISON_TYPES = [
        self::COMPARISON_TYPE_EQUAL,
        self::COMPARISON_TYPE_SMALLER,
        self::COMPARISON_TYPE_SMALLER_OR_EQUAL,
        self::COMPARISON_TYPE_GREATER,
        self::COMPARISON_TYPE_GREATER_OR_EQUAL,
        self::COMPARISON_TYPE_NOT_EQUAL,
        self::COMPARISON_TYPE_IN,
        self::COMPARISON_TYPE_NOT_IN,
        self::COMPARISON_TYPE_NULL,
        self::COMPARISON_TYPE_NOT_NULL
    ];

    /** @var string */
    protected $comparisonType;

    /** @var string|array due to comparisonType */
    protected $comparisonValue;

    /**
     * @param array $numberFilter
     * @throws \Exception
     */
    public function __construct(array $numberFilter)
    {
        if (!array_key_exists(self::FILED_NAME_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::FIELD_TYPE_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::COMPARISON_TYPE_FILTER_KEY, $numberFilter)
            || !array_key_exists(self::COMPARISON_VALUE_FILTER_KEY, $numberFilter)
        ) {
            throw new \Exception(sprintf('Either parameters: %s, %s, %s, %s does not exist in text filter',
                self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
        }

        $this->fieldName = $numberFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $numberFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->comparisonType = $numberFilter[self::COMPARISON_TYPE_FILTER_KEY];
        $this->comparisonValue = $numberFilter[self::COMPARISON_VALUE_FILTER_KEY];

        // validate comparisonType
        $this->validateComparisonType();

        // validate comparisonValue
        $this->validateComparisonValue();
    }

    /**
     * @return mixed
     */
    public function getComparisonType()
    {
        return $this->comparisonType;
    }

    /**
     * @return mixed
     */
    public function getComparisonValue()
    {
        return $this->comparisonValue;
    }

    /**
     * validate ComparisonType
     *
     * @throws \Exception
     */
    private function validateComparisonType()
    {
        if (!in_array($this->comparisonType, self::$SUPPORTED_COMPARISON_TYPES)) {
            throw new \Exception(sprintf('Not supported comparisonType %s', $this->comparisonType));
        }
    }

    /**
     * validate ComparisonValue
     *
     * @throws \Exception
     */
    private function validateComparisonValue()
    {
        // expect array
        if ($this->comparisonType == self::COMPARISON_TYPE_IN
            || $this->comparisonType == self::COMPARISON_TYPE_NOT_IN
        ) {
            if (!is_array($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType %s', $this->comparisonType));
            }

            foreach ($this->comparisonValue as $cv) {
                if (!is_numeric($cv)) {
                    throw new \Exception(sprintf('Expect comparisonValue is array of numeric with comparisonType %s', $this->comparisonType));
                }
            }
        } else {
            if ($this->comparisonType == self::COMPARISON_TYPE_NULL || $this->comparisonType == self::COMPARISON_TYPE_NOT_NULL){
                return;
            }
            // expect single value
            elseif (!is_numeric($this->comparisonValue)) {
                throw new \Exception(sprintf('Expect comparisonValue is numeric with comparisonType %s', $this->comparisonType));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function doFilter(ReportResult $reportsCollections)
    {
        switch ($this->getComparisonType()) {
            case self::COMPARISON_TYPE_EQUAL:
                return $this->equalFilter($reportsCollections);
            case self::COMPARISON_TYPE_NOT_EQUAL:
                return $this->notEqualFilter($reportsCollections);
            case self::COMPARISON_TYPE_SMALLER:
                return $this->smallerFilter($reportsCollections);
            case self::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                return $this->smallerOrEqualFilter($reportsCollections);
            case self::COMPARISON_TYPE_GREATER:
                return $this->greaterFilter($reportsCollections);
            case self::COMPARISON_TYPE_GREATER_OR_EQUAL:
                return $this->greaterOrEqualFilter($reportsCollections);
            case self::COMPARISON_TYPE_IN:
                return $this->inFilter($reportsCollections);
            case self::COMPARISON_TYPE_NOT_IN:
                return $this->notInFilter($reportsCollections);
            case self::COMPARISON_TYPE_NULL:
                return $this->nullFilter($reportsCollections);
            case self::COMPARISON_TYPE_NOT_NULL:
                return $this->notNullFilter($reportsCollections);
            default:
                return $reportsCollections;
        }
    }

    protected function equalFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10); //Not use: $report[$this->getFieldName()] == $this->getComparisonValue()
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport == $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function smallerFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport < $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function smallerOrEqualFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10); //Not use: $report[$this->getFieldName()] == $this->getComparisonValue()
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport <= $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function greaterFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport > $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function greaterOrEqualFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport >= $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function notEqualFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValue = round($this->getComparisonValue(), 10);

            return ($valueInReport != $compareValue);
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function inFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValues = array_map(function ($value) {
                return round($value, 10);
            }, $this->getComparisonValue());

            return (in_array($valueInReport, $compareValues));
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function notInFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $valueInReport = round($report[$this->getFieldName()], 10);
            $compareValues = array_map(function ($value) {
                return round($value, 10);
            }, $this->getComparisonValue());

            return (!in_array($valueInReport, $compareValues));
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function nullFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }
            return null == $report[$this->getFieldName()];
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    protected function notNullFilter(ReportResult $reportsCollections)
    {
        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }
            return null != $report[$this->getFieldName()];
        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }
}