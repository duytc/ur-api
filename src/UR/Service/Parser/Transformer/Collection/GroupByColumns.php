<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

class GroupByColumns implements CollectionTransformerInterface
{
    protected $groupByColumns;

    public function __construct(array $groupByColumns)
    {
        $this->groupByColumns = $groupByColumns;
    }

    public function transform(Collection $collection)
    {
        $rows = array_values($collection->getRows());

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
            $rows = self::group($rows, $groupColumnKeys, $sumFieldKeys);
        }

        return new Collection($collection->getColumns(), $rows);
    }

    /**
     * Array grouping
     *
     * @param array $array
     * @param array $groupFields array of grouping fields
     * @param array $sumFields array of summing fields
     * @param string $separator
     *
     * @return array
     */
    public static function group(array $array, array $groupFields, array $sumFields = array(), $separator = ',')
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
                        if (is_numeric($element[$sumField]) && false === strpos($sumFieldKey, 'as_string')) {
                            $result[$key][$sumField] += $element[$sumField];
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

    /**
     * @inheritdoc
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_GROUP;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }
}