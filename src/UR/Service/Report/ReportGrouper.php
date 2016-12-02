<?php
namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSetManagerInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\WeightedCalculationInterface;
use UR\Util\CalculateRatiosTrait;
use UR\Util\CalculateWeightedValueTrait;

class ReportGrouper implements ReportGrouperInterface
{
    use CalculateRatiosTrait;
    use CalculateWeightedValueTrait;
    use ColumnUtilTrait;

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
     * @param $singleDataSet
     * @return ReportResult
     */
    public function group(Collection $collection, array $metrics, $weightedCalculation, $singleDataSet = false)
    {
        if (count($collection->getRows()) < 1) {
            throw new NotFoundHttpException('can not find the report');
        }

        if ($this->isAssociativeArray($metrics) === false) {
            $metrics = array_keys($metrics);
            $metrics = array_intersect($metrics, array_keys($collection->getColumns()));
        } else {
            $metrics = array_intersect($metrics, $collection->getColumns());
        }
        $rows = $collection->getRows();

        $total = $rows[0];
        foreach($total as $key=>$value) {
            if (!in_array($collection->getTypeOf($key), ['number', 'decimal'])) {
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

                if (in_array($collection->getTypeOf($metric), ['number', 'decimal'])) {
                    $total[$metric] += $row[$metric];
                }
            }
        }

        $count = count($collection->getRows());
        $average = $total;
        foreach($metrics as $metric) {
            if (!in_array($collection->getTypeOf($metric), ['number', 'decimal'])) {
                continue;
            }
            $average[$metric] = $total[$metric] / $count;
        }

        $columns = $collection->getColumns();
        if ($this->isAssociativeArray($columns) === false) {
            $headers = $columns;
        } else {
            $headers = [];
            foreach($columns as $index => $column) {
                $headers[$column] = $this->convertColumn($column, $singleDataSet);
            }
        }

        return new ReportResult($rows, $total, $average, $headers);
    }

    protected function getDataSetManager()
    {
        return $this->dataSetManager;
    }

    protected function isAssociativeArray($array)
    {
        return array_values($array) === $array;
    }
}