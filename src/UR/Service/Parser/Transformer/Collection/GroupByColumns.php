<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Behaviors\ParserUtilTrait;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;

class GroupByColumns implements CollectionTransformerInterface
{
    use ParserUtilTrait;

    const TIMEZONE_KEY = 'timezone';
    const AGGREGATION_FIELDS_KEY = 'aggregationFields';
    const AGGREGATE_ALL_KEY = 'aggregateAll';
    const DEFAULT_TIMEZONE = 'UTC';
    const TEMPORARY_DATE_FORMAT = 'Y--m--d--H--i--s';

    /**
     * @var array
     */
    protected $groupByColumns;

    /**
     * @var string
     */
    protected $timezone;

    /**
     * @var array
     */
    protected $aggregationFields;

    /**
     * @var bool
     */
    protected $aggregateAll;

    /**
     * GroupByColumns constructor.
     * @param array $config
     * @param array $aggregationFields
     * @param bool $aggregateAll
     * @param string $timezone
     */
    public function __construct(array $config, $aggregateAll = true, array $aggregationFields, $timezone = self::DEFAULT_TIMEZONE)
    {
        $this->groupByColumns = $config;
        $this->timezone = $timezone;
        $this->aggregateAll = $aggregateAll;
        $this->aggregationFields = $aggregationFields;

    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();

        if ($rows->count() < 1) {
            return $collection;
        }
        $columns = array_keys($rows[0]);
        $groupColumnKeys = array_intersect($columns, $this->groupByColumns);

        $sumFieldKeys = [];
        if ($this->aggregateAll) {
            $sumFieldKeys = array_diff($columns, $groupColumnKeys);
        } else {
            foreach ($connectedDataSource->getMapFields() as $fieldInFile => $fieldInDataSet) {
                if (in_array($fieldInDataSet, $this->aggregationFields)) {
                    $sumFieldKeys[] = $fieldInFile;
                }
            }

            foreach ($this->aggregationFields as $fieldInDataSet) {
                if (in_array($fieldInDataSet, $columns)) {
                    $sumFieldKeys[] = $fieldInDataSet;
                }
            }
        }

        $groupedReports = $this->generateGroupedArray($groupColumnKeys, $collection);

        $results = new SplDoublyLinkedList();
        foreach ($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach ($result as $key => $value) {
                if (in_array($key, $sumFieldKeys)) {
                    if (in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL]) && in_array($key, $sumFieldKeys)) {
                        $result[$key] = 0;
                    }
                }
            }

            foreach ($groupedReport as $report) {
                foreach ($report as $key => $value) {
                    if (in_array($key, $sumFieldKeys)) {
                        if (array_key_exists($key, $result) && $value && in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL]) && in_array($key, $sumFieldKeys)) {
                            $result[$key] += $value;
                        }
                    }
                }
            }

            $results->push($result);
        }

        $collection->setRows($results);

        return $collection;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return array
     */
    public function getGroupByColumns(): array
    {
        return $this->groupByColumns;
    }

    /**
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    /**
     * @return array
     */
    public function getAggregationFields()
    {
        return $this->aggregationFields;
    }

    /**
     * @param array $aggregationFields
     */
    public function setAggregationFields($aggregationFields)
    {
        $this->aggregationFields = $aggregationFields;
    }

    /**
     * @return boolean
     */
    public function isAggregateAll()
    {
        return $this->aggregateAll;
    }

    /**
     * @param boolean $aggregateAll
     */
    public function setAggregateAll($aggregateAll)
    {
        $this->aggregateAll = $aggregateAll;
    }
}