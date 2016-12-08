<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;
use UR\Service\StringUtilTrait;

class GroupByTransform extends AbstractTransform implements GroupByTransformInterface
{
    use StringUtilTrait;
    const PRIORITY = 2;

    const FIELDS_KEY = 'fields';
    /**
     * @var array
     */
    protected $fields;

    function __construct(array $data)
    {
        parent::__construct();
        $this->fields = $data;
    }

    public function addField($field)
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, $joinBy = null)
    {
        $results = $this->getGroupedReport($this->getFields(), $collection, $metrics, $dimensions, $joinBy);
        $collection->setRows($results);
        $collection->setColumns(array_merge($metrics, $dimensions));

        return $collection;
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {

    }

    /**
     * @param $groupingFields
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return array
     */
    protected function getGroupedReport($groupingFields, Collection $collection, array $metrics, array &$dimensions, $joinBy = null)
    {
        $groupedReports = $this->generateGroupedArray($groupingFields, $collection, $dimensions, $joinBy);
        foreach($groupingFields as $index=>$groupingField) {
            $groupingFields[$index] = $this->removeIdSuffix($groupingField);
        }

        $results = [];
        foreach ($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach ($result as $key => $value) {
                if (in_array($key, $metrics) && in_array($collection->getTypeOf($key), ['number', 'decimal'])) {
                    $result[$key] = 0;
                }
            }

            foreach ($groupedReport as $report) {
                foreach ($report as $key => $value) {
                    if (in_array($collection->getTypeOf($key), ['number', 'decimal'])) {
                        $result[$key] += $value;
                    }
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param $groupingFields
     * @param Collection $collection
     * @param $dimensions
     * @param $joinBy
     * @return array
     * @throws \Exception
     */
    protected function generateGroupedArray($groupingFields, Collection $collection, &$dimensions, $joinBy = null)
    {
        $groupedArray = [];
        $rows = $collection->getRows();

        foreach($groupingFields as $index=>$groupingField) {
            if ($joinBy === $this->removeIdSuffix($groupingField)) {
                $groupingFields[$index] = $joinBy;
            }
        }

        foreach ($rows as $report) {
            $key = '';
            foreach ($groupingFields as $groupField) {
                if (!in_array($groupField, $dimensions)) {
                    throw new InvalidArgumentException(sprintf('%s is not a dimensions', $groupField));
                }

                if (array_key_exists($groupField, $report)) {
                    $key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
                }
            }

            $key = md5($key);
            $groupedArray[$key][] = $report;
        }

        return $groupedArray;
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }
}