<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Exception\RuntimeException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\Column\DateFormat;

class GroupByColumns implements CollectionTransformerInterface
{
    const TIMEZONE_KEY = 'timezone';
    const DEFAULT_TIMEZONE = 'UTC';

    protected $groupByColumns;
    protected $timezone;

    public function __construct(array $config, $timezone = self::DEFAULT_TIMEZONE)
    {
        $this->groupByColumns = $config;
        $this->timezone = $timezone;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = array_values($collection->getRows());
        $types = $collection->getTypes();

        if (count($rows) < 1) {
            return $collection;
        }
        $columns = [];

        foreach ($rows as $row) {
            $columns = array_keys($row);
            break;
        }

        // separate this so it's easy to debug
        $groupColumnKeys = array_intersect($columns, $this->groupByColumns);
        $sumFieldKeys = array_diff($columns, $groupColumnKeys);

        if (!empty($groupColumnKeys)) {
            $rows = self::group($rows, $types, $groupColumnKeys, $sumFieldKeys, $fromDateFormats, $mapFields);
        }

        return new Collection($collection->getColumns(), $rows);
    }

    /**
     * Array grouping
     *
     * @param array $array
     * @param array $types
     * @param array $groupFields array of grouping fields
     * @param array $sumFields array of summing fields
     * @param array $fromDateFormats
     * @param array $mapFields
     *
     * @return array
     */
    public function group(array $array, array $types, array $groupFields, array $sumFields = array(), $fromDateFormats = [], $mapFields = [])
    {
        $result = [];

        if (0 === count($array) || 0 === count($groupFields)) {
            return $result;
        }

        $arr = [];
        foreach ($array as $element) {
            //calc key
            $key = '';
            $newElement = [];
            foreach ($groupFields as $groupField) {
                if (array_key_exists($groupField, $element)) {
                    if (array_key_exists($groupField, $types) && $types[$groupField] == FieldType::DATETIME) {
                        $normalizedDate = $this->normalizeTimezone($element[$groupField], $groupField, $fromDateFormats, $mapFields, $fromDateFormat);
                        $newElement[$groupField] = $normalizedDate->setTime(0,0)->format('Y-m-d H:i:s T');
                        $key .= $normalizedDate->format('Y-m-d');
                        continue;
                    }

                    $key .= is_array($element[$groupField]) ? json_encode($element[$groupField], JSON_UNESCAPED_UNICODE) : $element[$groupField];
                    $newElement[$groupField] = $element[$groupField];
                }
            }
            $key = md5($key);

            //add fields
            if (!array_key_exists($key, $result)) {
                $result[$key] = $newElement;
                $arr[$key] = $newElement;
            }

            //add sum
            foreach ($sumFields as $sumFieldKey => $sumField) {
                if (array_key_exists($sumField, $element)) {
                    $isFirst = false;
                    if (!array_key_exists($sumField, $result[$key])) {
                        $arr[$key][$sumField][] = $element[$sumField];
                        $result[$key][$sumField] = $element[$sumField];
                        $isFirst = true;
                    }

                    if (!$isFirst) {
                        $isSumField = array_key_exists($sumField, $types) && ($types[$sumField] == FieldType::NUMBER || $types[$sumField] == FieldType::DECIMAL);

                        if ($isSumField) {
                            if ($result[$key][$sumField] === null && $element[$sumField] === null) {
                                $result[$key][$sumField] = null;
                            } else {
                                $result[$key][$sumField] += $element[$sumField];
                            }
                        } else {
                            $arr[$key][$sumField][] = $element[$sumField];
                            $x = $arr[$key][$sumField][0];
                            $result[$key][$sumField] = $x;
                        }
                    }
                }
            }
        }

        return array_values($result);
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
     * @param $value
     * @param $groupField
     * @param $fromDateFormats
     * @param $mapFields
     * @param $dateFormat
     * @return \DateTime
     */
    private function normalizeTimezone($value, $groupField, $fromDateFormats, $mapFields, &$dateFormat)
    {
        $dateFormats = [];
        $timezone = 'UTC';

        if (!isset($mapFields[$groupField])) {
            throw new RuntimeException(sprintf('Missing map field for %s', $groupField));
        }

        foreach ($fromDateFormats as $column => $formats) {
            if ($column == $mapFields[$groupField]) {
                $dateFormats = $formats['formats'];
                $timezone = $formats['timezone'];
                break;
            }
        }

        foreach ($dateFormats as $format) {
            $dateFormat = DateFormat::convertCustomFromDateFormat($format['format']);
            $date = \DateTime::createFromFormat($dateFormat, $value, new \DateTimeZone($timezone));
            if (!$date instanceof \DateTime) {
                continue;
            }

            return $date->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));
        }

        throw new RuntimeException('not found any invalid date format');
    }
}