<?php


namespace UR\Domain\DTO\Report\Formats;


use SplDoublyLinkedList;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Report\ReportResultInterface;

class CurrencyFormat extends AbstractFormat implements CurrencyFormatInterface
{
    const CURRENCY_KEY = 'currency';

    const DEFAULT_CURRENCY = '$';

    const CONVERT_EMPTY_VALUE_TO_ZERO_KEY = 'convertEmptyValueToZero';

    /** @var bool */
    protected $isConvertEmptyValueToZero;

    /** @var int */
    protected $currency;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::CURRENCY_KEY, $data)) {
            throw new InvalidArgumentException('"currency" is missing');
        }

        $this->currency = empty($data[self::CURRENCY_KEY]) ? self::DEFAULT_CURRENCY : $data[self::CURRENCY_KEY];

        $this->isConvertEmptyValueToZero = (array_key_exists(self::CONVERT_EMPTY_VALUE_TO_ZERO_KEY, $data))
            ? (bool)$data[self::CONVERT_EMPTY_VALUE_TO_ZERO_KEY]
            : false;
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
        $rows = $reportResult->getRows();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();
        $calculatedMetricsResult = $reportResult->getCalculatedMetricsResult();

        $fields = $this->getFields();

        /* format for all records of reports */
        gc_enable();
        $newRows = new SplDoublyLinkedList();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = $this->formatOneCurrency($row[$field]);
            }
            $newRows->push($row);
            unset($row);
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

        /* format for calculated metrics */
        $newCalculatedMetricsResult = $calculatedMetricsResult;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $calculatedMetricsResult)) {
                continue;
            }

            $newCalculatedMetricsResult[$field] = $this->formatOneCurrency($calculatedMetricsResult[$field]);
        }

        /* set value again */
        unset($rows, $row);
        gc_collect_cycles();
        $reportResult->setRows($newRows);
        $reportResult->setTotal($newTotals);
        $reportResult->setAverage($newAverages);
        $reportResult->setCalculatedMetricsResult($newCalculatedMetricsResult);
    }

    /**
     * format one currency
     *
     * @param $fieldValue
     * @return string
     */
    private function formatOneCurrency($fieldValue)
    {
        if ($fieldValue === null || $fieldValue === '') { // do not check by empty() because empty(0) return true
            if ($this->isConvertEmptyValueToZero) {
                $fieldValue = 0;
            } else {
                return $fieldValue;
            }
        }

        return $this->getCurrency() . ' ' . $fieldValue;
    }
}