<?php


namespace UR\Domain\DTO\Report\Formats;


use DateTime;
use SplDoublyLinkedList;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Report\ReportResultInterface;

class DateFormat extends AbstractFormat implements DateFormatInterface
{
    const OUTPUT_FORMAT_KEY = 'format';

    /** @var string */
    protected $outputFormat;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::OUTPUT_FORMAT_KEY, $data)) {
            throw new InvalidArgumentException('"format" is missing');
        }

        $this->outputFormat = $data[self::OUTPUT_FORMAT_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getOutputFormat()
    {
        return $this->outputFormat;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_DATE;
    }

    /**
     * @inheritdoc
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions)
    {
        $rows = $reportResult->getRows();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        $fields = $this->getFields();

        /* format for all records of reports */
        $newRows = new SplDoublyLinkedList();
        gc_enable();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = $this->formatOneDate($row[$field]);
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

            $newTotals[$field] = $this->formatOneDate($totals[$field]);
        }

        /* format for averages */
        $newAverages = $averages;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $averages)) {
                continue;
            }

            $newAverages[$field] = $this->formatOneDate($averages[$field]);
        }

        /* set value again */
        unset($rows, $row);
        gc_collect_cycles();
        $reportResult->setRows($newRows);
        $reportResult->setTotal($newTotals);
        $reportResult->setAverage($newAverages);
    }

    /**
     * format one date
     *
     * @param $fieldValue
     * @return string|null null if can not format
     */
    private function formatOneDate($fieldValue)
    {
        try {
            $date = date_create_from_format('Y-m-d', $fieldValue);
            if (!$date instanceof DateTime) {
                $date = date_create_from_format('Y-m-d H:i:s', $fieldValue);
            }
            if (!$date instanceof DateTime) {
                throw new \Exception(sprintf('System can not create date from value: %s', $fieldValue));
            }

            return $date->format($this->outputFormat);
        } catch (\Exception $e) {
            return null;
        }
    }
}