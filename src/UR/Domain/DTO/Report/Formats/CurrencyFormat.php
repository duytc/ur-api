<?php


namespace UR\Domain\DTO\Report\Formats;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Report\ReportResultInterface;

class CurrencyFormat extends AbstractFormat implements CurrencyFormatInterface
{
    const CURRENCY_KEY = 'currency';

    const DEFAULT_CURRENCY = '$';

    /** @var int */
    protected $currency;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::CURRENCY_KEY, $data)) {
            throw new InvalidArgumentException('"currency" is missing');
        }

        $this->currency = empty($data[self::CURRENCY_KEY]) ? self::DEFAULT_CURRENCY : $data[self::CURRENCY_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_CURRENCY;
    }

    /**
     * @inheritdoc
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions)
    {
        $reports = $reportResult->getReports();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        $fields = $this->getFields();

        /* format for all records of reports */
        $newReports = [];
        foreach ($reports as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = $this->formatOneCurrency($row[$field]);
            }

            $newReports[] = $row;
        }

        /* format for totals */
        $newTotals = $totals;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $totals)) {
                continue;
            }

            $newTotals[$field] = $this->formatOneCurrency($totals[$field]);
        }

        /* format for averages */
        $newAverages = $averages;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $averages)) {
                continue;
            }

            $newAverages[$field] = $this->formatOneCurrency($averages[$field]);
        }

        /* set value again */
        $reportResult->setReports($newReports);
        $reportResult->setTotal($newTotals);
        $reportResult->setAverage($newAverages);
    }

    /**
     * format one currency
     *
     * @param $fieldValue
     * @return string
     */
    private function formatOneCurrency($fieldValue)
    {
        // do not format currency if value not set
        if (null === $fieldValue || '' === $fieldValue) {
            return $fieldValue;
        }

        return $this->getCurrency() . ' ' . $fieldValue;
    }
}