<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class GroupByTransform extends AbstractTransform implements GroupByTransformInterface
{
    const FIELDS_KEY = 'fields';
    /**
     * @var array
     */
    protected $fields;

    function __construct(array $data)
    {
        if (!array_key_exists(self::FIELDS_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        if (!is_array($data[self::FIELDS_KEY])) {
            throw new InvalidArgumentException(' invalid "fields" is provided');
        }

        $this->fields = $data[self::FIELDS_KEY];
    }

    public function addField($field)
    {
        $this->fields [] = $field;
        return $this;
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function transform(Collection $collection, array $metrics, array $dimensions)
    {
       $results =  $this->getGroupedReport($this->getFields(), $collection, $metrics, $dimensions);
        $collection->setRows($results);

        return $collection;
    }

    /**
     * @param $groupingFields
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return array
     */
    protected function getGroupedReport($groupingFields, Collection $collection, array $metrics, array $dimensions)
    {
        $groupedReports = $this->generateGroupedArray($groupingFields, $collection, $dimensions);

        $results = [];
        foreach ($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach ($result as $key => $value) {
                if (in_array($key, $metrics)) {
                    $result[$key] = 0;
                }
            }

            foreach ($groupedReport as $report) {
                foreach ($report as $key => $value) {
                    if (in_array($key, $metrics)) {
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
     * @return array
     */
    protected function generateGroupedArray($groupingFields, Collection $collection, $dimensions)
    {
        $groupedArray = [];
        $rows = $collection->getRows();
        foreach ($rows as $report) {
            $key = '';
            foreach ($groupingFields as $groupField) {
                if (array_key_exists($groupField, $report)) {
                    $key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
                }
            }

            //Note: Remove all dimensions that do not group
            foreach ($dimensions as $dimension) {
                if (in_array($dimension, $groupingFields)) {
                    continue;
                }
                unset($report[$dimension]);
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