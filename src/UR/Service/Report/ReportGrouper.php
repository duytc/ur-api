<?php
namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSetManagerInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\DataSet\FieldType;
use UR\Service\DateUtil;
use UR\Service\DateUtilInterface;
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
     * @var DateUtilInterface
     */
    protected $dateUtil;

    /**
     * ReportGrouper constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param DateUtilInterface $dateUtil
     */
    public function __construct(DataSetManagerInterface $dataSetManager, DateUtilInterface $dateUtil)
    {
        $this->dataSetManager = $dataSetManager;
        $this->dateUtil = $dateUtil;
    }


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param $weightedCalculation
     * @param $dateRanges
     * @param $isShowDataSetName
     * @param bool $singleDataSet
     * @return ReportResult
     */
    public function group(Collection $collection, array $metrics, $weightedCalculation, $dateRanges, $isShowDataSetName, $singleDataSet = false)
    {
        if (count($collection->getRows()) < 1) {
            throw new NotFoundHttpException('can not find the report');
        }

        $metrics = array_intersect($metrics, $collection->getColumns());
        $rows = $collection->getRows();

        $total = [];
        foreach ($metrics as $key) {
            if (in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL])) {
                $total[$key] = 0;
            }
        }

        // aggregate weighted field
        if ($weightedCalculation instanceof WeightedCalculationInterface && $weightedCalculation->hasCalculation()) {
            foreach ($metrics as $index => $metric) {
                if (!$weightedCalculation->hasCalculatingField($metric)) {
                    continue;
                }

                $total[$metric] = $this->calculateWeightedValue($rows, $weightedCalculation->getFrequencyField($metric), $weightedCalculation->getWeightedField($metric));
                unset($metrics[$index]);
            }
        }

        // aggregate normal fields
        foreach ($rows as $row) {
            foreach ($metrics as $metric) {
                if (!array_key_exists($metric, $row)) {
                    $row[$metric] = null;
                }

                if (in_array($collection->getTypeOf($metric), [FieldType::NUMBER, FieldType::DECIMAL])) {
                    $total[$metric] += $row[$metric];
                }
            }
        }

        $count = count($collection->getRows());
        $average = $total;
        foreach ($metrics as $metric) {
            if (!in_array($collection->getTypeOf($metric), [FieldType::NUMBER, FieldType::DECIMAL])) {
                continue;
            }
            $average[$metric] = $total[$metric] / $count;
        }

        $columns = $collection->getColumns();
        $headers = [];
        foreach ($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column, $isShowDataSetName, $singleDataSet);
        }

        return new ReportResult($rows, $total, $average, $this->dateUtil->mergeDateRange($dateRanges), $headers, $collection->getTypes());
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