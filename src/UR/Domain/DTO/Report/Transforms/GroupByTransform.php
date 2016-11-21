<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class GroupByTransform extends AbstractTransform implements GroupByTransformInterface
{
    const PRIORITY = 2;
    const FIELDS_KEY = 'fields';
    /**
     * @var array
     */
    protected $fields;

    function __construct(array $data)
    {
        parent::__construct();
        if (!array_key_exists(self::FIELDS_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        if (!is_array($data[self::FIELDS_KEY])) {
            throw new InvalidArgumentException(' invalid "fields" is provided');
        }

        $this->fields = $data[self::FIELDS_KEY];
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function addField($field)
    {
        $this->fields [] = $field;
        return $this;
    }

    public function transform(Collection $collection)
    {
        $groupedReports = $this->generateGroupedArray($this->fields, $collection);

        $results = [];
        foreach ($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach ($result as $key => $value) {
//                if (in_array($key, $metrics)) {
//                    $result[$key] = 0;
//                }
                if (is_numeric($value)) {
                    $result[$key] = 0;
                }
            }

            foreach ($groupedReport as $report) {
                foreach ($report as $key => $value) {
                    if (is_numeric($value)) {
//                    if (in_array($key, $metrics)) {
                        $result[$key] += $value;
                    }
                }
            }

            $results[] = $result;
        }

        $collection->setRows($results);
    }

    protected function generateGroupedArray($groupingFields, Collection $collection)
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
//            foreach ($dimensions as $dimension) {
//                if (in_array($dimension, $groupingFields)) {
//                    continue;
//                }
//                unset($report[$dimension]);
//            }

            $key = md5($key);
            $groupedArray[$key][] = $report;

        }

        return $groupedArray;
    }
}