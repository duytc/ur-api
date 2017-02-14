<?php

namespace UR\Domain\DTO\Report\Formats;

use UR\Service\DTO\Report\ReportResultInterface;

class PercentageFormat extends AbstractFormat implements PercentageFormatInterface
{
    const PERCENTAGE_FORMAT_KEY = 'percentage';
    const PRECISION_KEY = 'precision';
    const DEFAULT_PRECISION = 2;
    const PERCENTAGE_FORMAT_SIGN = '%';

    /** @var int */
    protected $precision;

    /**
     * PercentageFormat constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->precision = $data[self::PRECISION_KEY];
    }

    /**
     * @return int
     */
    public function getPrecision(): int
    {
        return $this->precision;
    }


    /**
     * @return int
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_PERCENTAGE;
    }

    /**
     * @param ReportResultInterface $reportResult
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions)
    {
        $reports = $reportResult->getReports();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        $neededFormatFields = $this->getFields();
        foreach ($neededFormatFields as $neededFormatField) {
            foreach ($reports as $key => $report) {
                if (!array_key_exists($neededFormatField, $report)) {
                    continue;
                }
                $value = $report[$neededFormatField];
                if (!is_numeric($value)) {
                    continue;
                }
                $convertedString = $this->formatPercentage($value, $this->getPrecision());
                $report[$neededFormatField] = $convertedString;
                $reports[$key] = $report;
            }

            if (!array_key_exists($neededFormatField, $totals)) {
                continue;
            }

            $totalValue = $totals[$neededFormatField];
            if (!is_numeric($totalValue)) {
                continue;
            }
            $totals[$neededFormatField] = $this->formatPercentage($totalValue, $this->getPrecision());

            if (!array_key_exists($neededFormatField, $averages)) {
                continue;
            }
            $averageValue = $averages[$neededFormatField];
            if (!is_numeric($averageValue)) {
                continue;
            }
            $averages[$neededFormatField] = $this->formatPercentage($averageValue, $this->getPrecision());
        }

        $reportResult->setReports($reports);
        $reportResult->setTotal($totals);
        $reportResult->setAverage($averages);

        return $reportResult;
    }

    /**
     * @param $number
     * @param $precision
     * @return string
     */
    protected function formatPercentage($number, $precision)
    {
        if (is_null($number)) {
            return $number;
        }

        $convertedNumber = number_format($number * 100, $precision);
        $convertedString = (string)$convertedNumber . self::PERCENTAGE_FORMAT_SIGN;

        return $convertedString;
    }
}