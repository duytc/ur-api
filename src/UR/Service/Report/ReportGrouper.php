<?php


namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\WeightedCalculationInterface;
use UR\Util\CalculateRatiosTrait;
use UR\Util\CalculateWeightedValueTrait;

class ReportGrouper implements ReportGrouperInterface
{
    use CalculateRatiosTrait;
    use CalculateWeightedValueTrait;

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


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param $weightedCalculation
     * @return ReportResult
     */
    public function group(Collection $collection, array $metrics, $weightedCalculation)
    {
        if (count($collection->getRows()) < 1) {
            throw new NotFoundHttpException('can not find the report');
        }

        $metrics = array_intersect($metrics, $collection->getColumns());
        $rows = $collection->getRows();

        $total = $rows[0];
        foreach($total as $key=>$value) {
            if (!in_array($key, $metrics)) {
                unset($total[$key]);
                continue;
            }

            $total[$key] = 0;
        }
//        foreach($metrics as $metric) {
//            //reset metrics
//            $total[$metric] = 0;
//        }

        // aggregate weighted field
        if ($weightedCalculation instanceof WeightedCalculationInterface && $weightedCalculation->hasCalculation()) {
            foreach($metrics as $index => $metric) {
                if (!$weightedCalculation->hasCalculatingField($metric)) {
                    continue;
                }

                $total[$metric ] = $this->calculateWeightedValue($rows, $weightedCalculation->getFrequencyField($metric), $weightedCalculation->getWeightedField($metric));
                unset($metrics[$index]);
            }
        }

        // aggregate normal fields
        foreach($rows as $row) {
            foreach($metrics as $metric) {
                if (!array_key_exists($metric, $row)) {
                    continue;
                }

                if (is_numeric($row[$metric])) {
                    $total[$metric] += $row[$metric];
                }
            }
        }

        $count = count($collection->getRows());
        $average = $total;
        foreach($metrics as $metric) {
            $average[$metric] = $total[$metric] / $count;
        }

        $columns = array_unique($collection->getColumns());
        $headers = [];
        foreach($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column);
        }

        return new ReportResult($rows, $total, $average, $headers);
    }

    /**
     * Convert column name in underscore format with data set id to real name
     * @param $column
     * @return mixed|string
     */
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