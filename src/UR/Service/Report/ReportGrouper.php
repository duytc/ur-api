<?php


namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Util\CalculateRatiosTrait;

class ReportGrouper implements ReportGrouperInterface
{
    use CalculateRatiosTrait;
    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /**
     * ReportGrouper constructor.
     * @param DataSetManagerInterface $dataSetManager
     */
    public function __construct(DataSetManagerInterface $dataSetManager)
    {
        $this->dataSetManager = $dataSetManager;
    }


    public function group(Collection $collection, array $metrics)
    {
        if (count($collection->getRows()) < 1) {
            throw new NotFoundHttpException('can not find the report');
        }

//        $total = [];
//        $average = [];
        $metrics = array_intersect($metrics, $collection->getColumns());
        $rows = $collection->getRows();

        $total = $rows[0];
        foreach($metrics as $metric) {
            //reset metrics
            $total[$metric] = 0;
        }

        foreach($rows as $row) {
            foreach($metrics as $metric) {
                //aggregate metrics
                if (is_numeric($row[$metric])) {
                    $total[$metric] += $row[$metric];
                }
            }
        }

        $count = count($collection->getRows());
        $average = $total;
        foreach($metrics as $metric) {
            $average[$metric] = $this->getRatio($total[$metric], $count);
        }
        $columns = $collection->getColumns();
        $headers = [];
        foreach($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column);
        }

        return new ReportResult($rows, $total, $average, $headers);
    }

    protected function convertColumn($column)
    {
        $lastOccur = strrchr($column, "_");
        $column = str_replace($lastOccur, "", $column);
        $dataSetId = str_replace("_", "", $lastOccur);
        $dataSetId = filter_var($dataSetId, FILTER_VALIDATE_INT);
        $column = ucwords(str_replace("_", " ", $column));
        $dataSet = $this->dataSetManager->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            return $column;
        }

        return sprintf("%s (%s)", $column, $dataSet->getName());
    }
}