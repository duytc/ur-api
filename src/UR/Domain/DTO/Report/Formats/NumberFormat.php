<?php


namespace UR\Domain\DTO\Report\Formats;


use SplDoublyLinkedList;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Report\ReportResultInterface;

class NumberFormat extends AbstractFormat implements NumberFormatInterface
{
    const PRECISION_KEY = 'decimals';
    const THOUSAND_SEPARATOR_KEY = 'thousandsSeparator';

    const SEPARATOR_DOT = '.';
    const SEPARATOR_COMMA = ',';
    const SEPARATOR_NONE = 'none';

    const DEFAULT_DECIMAL_SEPARATOR = self::SEPARATOR_DOT;
    const DEFAULT_THOUSAND_SEPARATOR = self::SEPARATOR_NONE;
    const DEFAULT_PRECISION = 3;

    static $SUPPORTED_THOUSAND_SEPARATORS = [
        self::SEPARATOR_COMMA,
        self::SEPARATOR_NONE
    ];

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

        // check if decimal supported
        $precision = $data[self::PRECISION_KEY];
        if (!is_numeric($precision) || $precision < 0) {
            throw new InvalidArgumentException('decimals must be number that greater than or equal 0');
        }

        // check if thousandSeparator supported
        $thousandSeparator = $data[self::THOUSAND_SEPARATOR_KEY];
        if (!in_array($thousandSeparator, self::$SUPPORTED_THOUSAND_SEPARATORS)) {
            throw new InvalidArgumentException('thousandSeparator is not supported');
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
        $rows = $reportResult->getRows();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        $decimalSeparator = self::SEPARATOR_DOT;
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

                $row[$field] = $this->formatOneNumber($row[$field], $decimalSeparator);
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
        unset($rows, $row);
        gc_collect_cycles();
        $reportResult->setRows($newRows);
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
        $thousandSeparator = $this->thousandSeparator === self::SEPARATOR_NONE ? '' : $this->thousandSeparator;

	    if ($fieldValue ===  null) {
		    return $fieldValue;
	    }

        return number_format($fieldValue, $this->precision, $decimalSeparator, $thousandSeparator);
    }
}