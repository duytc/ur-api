<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use PDO;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportCollection;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\DomainManager\DataSetManagerInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\DataSet\FieldType;
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
     * @var SqlBuilderInterface
     */
    protected $sqlBuilder;

    /**
     * ReportGrouper constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param DateUtilInterface $dateUtil
     * @param SqlBuilderInterface $sqlBuilder
     */
    public function __construct(DataSetManagerInterface $dataSetManager, DateUtilInterface $dateUtil, SqlBuilderInterface $sqlBuilder)
    {
        $this->dataSetManager = $dataSetManager;
        $this->dateUtil = $dateUtil;
        $this->sqlBuilder = $sqlBuilder;
    }


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param $weightedCalculation
     * @param $dateRanges
     * @param $isShowDataSetName
     * @return ReportResult
     */
    public function groupForMultiView(Collection $collection, array $metrics, $weightedCalculation, $dateRanges, $isShowDataSetName)
    {
        $columns = $collection->getColumns();
        $headers = [];
        foreach ($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column, $isShowDataSetName);
        }

        if (count($collection->getRows()) < 1) {
            return new ReportResult([], [], [], $this->dateUtil->mergeDateRange($dateRanges), $headers, $collection->getTypes());
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

        return new ReportResult($rows, $total, $average, $this->dateUtil->mergeDateRange($dateRanges), $headers, $collection->getTypes());
    }

    public function groupForSingleView($subQuery, Collection $collection, ParamsInterface $params, $overridingFilters = null)
    {
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            $stmt = $this->sqlBuilder->buildGroupQueryForSingleDataSet($subQuery, $dataSets[0], $params->getTransforms(), $params->getSearches(), $params->getShowInTotal(), $overridingFilters);
        } else {
            $stmt = $this->sqlBuilder->buildGroupQuery($subQuery, $dataSets, $params->getJoinConfigs(), $params->getTransforms(), $params->getSearches(), $params->getShowInTotal(), $overridingFilters);
        }

        $columns = $collection->getColumns();
        $headers = [];
        $total = [];
        $average = [];
        $totalReport = 0;
        foreach ($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column, $params->getIsShowDataSetName());
        }

        $hasGroup = false;
        $transforms = $params->getTransforms();
        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $hasGroup = true;
                break;
            }
        }

        if ($stmt instanceof Statement) {
            $rows = $stmt->fetchAll();
            $count = count($rows);

            // has group transform
            if ($count > 1) {
                $totalReport = $count;
                foreach ($rows as $row) {
                    foreach ($row as $key => $value) {
                        if (!array_key_exists($key, $total)) {
                            $total[$key] = (float) $value;
                            continue;
                        }

                        $total[$key] += (float) $value;
                    }
                }
            } else {
                $total = $rows[0];
//                if ($hasGroup) {
//                    $totalReport = 1;
//                } else {
                    $totalReport = intval($total['total']);
//                }
            }

            unset($total['total']);
            $average = $total;
            foreach ($average as $key => &$value) {
                $value = round($total[$key] / $totalReport, 4);
            }
        }

        return new ReportResult($collection->getRows(), $total, $average, null, $headers, $collection->getTypes(), $totalReport);
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