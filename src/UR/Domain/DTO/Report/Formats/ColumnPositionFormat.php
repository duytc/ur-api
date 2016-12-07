<?php


namespace UR\Domain\DTO\Report\Formats;


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
        $reports = $reportResult->getReports();
        $column = $reportResult->getColumns();
        $columnOrder = $this->getFields();


        $newReports = [];
        foreach ($reports as $report) {
            $newReports [] = $this->changeIndex($report, $columnOrder);
        }

        $newColumns = $this->changeIndex($column, $columnOrder);

        $reportResult->setReports($newReports);
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

        foreach ($elementOrders as $column) {
            if (!in_array($column, $columnsOfReport)) {
                throw new \Exception(sprintf('Column %s not exit in report', $column));
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

        foreach ($report as $key=>$remainElement) {
            $output[$key] = $remainElement;
        }

        return $output;
    }
}