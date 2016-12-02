<?php


namespace UR\Domain\DTO\Report\Formats;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Report\ReportResultInterface;

class NumberFormat extends AbstractFormat implements NumberFormatInterface
{
    const PRECISION_KEY = 'decimals';
    const THOUSAND_SEPARATOR_KEY = 'thousandsSeparator';

    const DEFAULT_DECIMAL_SEPARATOR = '.';
    const DEFAULT_THOUSAND_SEPARATOR = ',';
    const DEFAULT_PRECISION = 3;

    /** @var int */
    protected $precision;

    /** @var string */
    protected $thousandSeparator;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::PRECISION_KEY, $data) || !array_key_exists(self::THOUSAND_SEPARATOR_KEY, $data)) {
            throw new InvalidArgumentException('either "decimals" or "thousandsSeparator" is missing');
        }

        $this->precision = (0 != $data[self::PRECISION_KEY] && empty($data[self::PRECISION_KEY])) ? self::DEFAULT_PRECISION : $data[self::PRECISION_KEY];
        $this->thousandSeparator = empty($data[self::THOUSAND_SEPARATOR_KEY]) ? self::DEFAULT_THOUSAND_SEPARATOR : $data[self::THOUSAND_SEPARATOR_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @inheritdoc
     */
    public function getThousandSeparator()
    {
        return $this->thousandSeparator;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_NUMBER;
    }

    /**
     * @inheritdoc
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions)
    {
        $reports = $reportResult->getReports();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        $decimalSeparator = !strcmp($this->thousandSeparator, self::DEFAULT_THOUSAND_SEPARATOR) ? self::DEFAULT_DECIMAL_SEPARATOR : self::DEFAULT_THOUSAND_SEPARATOR;
        $fields = $this->getFields();

        /* format for all records of reports */
        $newReports = [];
        foreach ($reports as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = $this->formatOneNumber($row[$field], $decimalSeparator);
            }

            $newReports[] = $row;
        }

        /* format for totals */
        $newTotals = $totals;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $totals)) {
                continue;
            }

            $newTotals[$field] = $this->formatOneNumber($totals[$field], $decimalSeparator);
        }

        /* format for averages */
        $newAverages = $averages;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $averages)) {
                continue;
            }

            $newAverages[$field] = $this->formatOneNumber($averages[$field], $decimalSeparator);
        }

        /* set value again */
        $reportResult->setReports($newReports);
        $reportResult->setTotal($newTotals);
        $reportResult->setAverage($newAverages);
    }

    /**
     * format one number
     *
     * @param $fieldValue
     * @param $decimalSeparator
     * @return string
     */
    private function formatOneNumber($fieldValue, $decimalSeparator)
    {
        return number_format($fieldValue, $this->precision, $decimalSeparator, $this->thousandSeparator);
    }
}