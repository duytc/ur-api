<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\DateUtilInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
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

    public function groupForSingleView(Collection $collection, ParamsInterface $params, $overridingFilters = null)
    {
        $dataSets = $params->getDataSets();
        $columns = $collection->getColumns();
        $headers = [];
        $total = [];
        $average = [];
        $totalReport = 0;
        foreach ($columns as $index => $column) {
            $headers[$column] = $this->convertColumn($column, $params->getIsShowDataSetName());
        }

        // make fields in show in total to an array
        $showInTotalFields = [];
        $showInTotals = $params->getShowInTotal();
        $showInTotals = is_array($showInTotals) ? $showInTotals : [$showInTotals];
        foreach ($showInTotals as $showInTotal) {
            if (!is_array($showInTotal)) {
                continue;
            }
            if (!array_key_exists('type', $showInTotal)) {
                continue;
            }
            if ($showInTotal['type'] == 'average') {
                $needToBeAverageFields = $showInTotal['fields'];
            }

            $showInTotalFields = array_merge($showInTotalFields, $showInTotal['fields']);
        }
        unset($showInTotal);

        if (count($dataSets) < 2) {
            $stmt = $this->sqlBuilder->buildGroupQueryForSingleDataSet($params, $dataSets[0], $params->getTransforms(), $params->getSearches(), $showInTotalFields, $overridingFilters);
        } else {
            $stmt = $this->sqlBuilder->buildGroupQuery($params, $dataSets, $params->getJoinConfigs(), $params->getTransforms(), $params->getSearches(), $showInTotalFields, $overridingFilters);
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
                $totalReport = intval($total['total']);
            }

            foreach ($total as $key => &$value) {
                // calculate average for the fields in type avarage
                if (isset($needToBeAverageFields)) {
                    if (in_array($key, $needToBeAverageFields)) {
                        $value = (float) $value / $totalReport;
                        continue;
                    }
                }
            }

            unset($total['total']);
            $average = $total;
            foreach ($average as $key => &$value) {
                $value = $total[$key] / $totalReport;
            }
        }
        
        $this->removeTemporaryTables($params);
        gc_collect_cycles();
        
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

    private function removeTemporaryTables($params)
    {
        return $this->sqlBuilder->removeTemporaryTables($params);
    }
}