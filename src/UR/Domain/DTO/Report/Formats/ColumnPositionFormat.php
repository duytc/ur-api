<?php


namespace UR\Domain\DTO\Report\Formats;


use SplDoublyLinkedList;
use UR\Service\DTO\Report\ReportResultInterface;

class ColumnPositionFormat extends AbstractFormat implements ColumnPositionFormatInterface
{
    const COLUMN_POSITION_KEY = 'columnPosition';

    function __construct(array $data)
    {
        parent::__construct($data);
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_COLUMN_POSITION;
    }

    /**
     * @param ReportResultInterface $reportResult
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions)
    {
        $rows = $reportResult->getRows();
        $column = $reportResult->getColumns();
        $columnOrder = $this->getFields();


        $newRows = new SplDoublyLinkedList();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            $newRows->push($this->changeIndex($row, $columnOrder));
            unset($row);
        }

        $newColumns = $this->changeIndex($column, $columnOrder);

        unset($rows, $row);
        $reportResult->setRows($newRows);
        $reportResult->setColumns($newColumns);
    }

    /**
     * @param array $report
     * @param array $elementOrders
     * @return array
     * @throws \Exception
     */
    protected function changeIndex(array $report, array $elementOrders)
    {
        $output = [];
        $columnsOfReport = array_keys($report);

        foreach ($elementOrders as $key=>$column) {
            if (!in_array($column, $columnsOfReport)) {
                unset($elementOrders[$key]);
            }
        }

        foreach ($elementOrders as $column) {
            $element = $report[$column];
            $output[$column] = $element;
            unset($report[$column]);
        }

        if (empty($report)) {
            return $output;
        }

        foreach ($report as $key => $remainElement) {
            $output[$key] = $remainElement;
        }

        return $output;
    }
}