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

    public function groupForSingleView($subQuery, Collection $collection, ParamsInterface $params, $overridingFilters = null)
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
        
        if (count($dataSets) < 2) {
            $stmt = $this->sqlBuilder->buildGroupQueryForSingleDataSet($subQuery, $dataSets[0], $params->getTransforms(), $params->getSearches(), $params->getShowInTotal(), $overridingFilters);
        } else {
            $stmt = $this->sqlBuilder->buildGroupQuery($subQuery, $dataSets, $params->getJoinConfigs(), $params->getTransforms(), $params->getSearches(), $params->getShowInTotal(), $overridingFilters);
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

            unset($total['total']);
            $average = $total;
            foreach ($average as $key => &$value) {
                $value = $total[$key] / $totalReport;
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