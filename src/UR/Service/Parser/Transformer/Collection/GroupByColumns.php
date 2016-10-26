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
        $columns = $collection->getColumns();
        $rows = $collection->getRows();

        // separate this so it's easy to debug
        $groupColumnKeys = array_intersect($columns, $this->groupByColumns);
        $sumFieldKeys = array_diff($columns, $groupColumnKeys);

        if (!empty($groupColumnKeys)) {
            $rows = self::group($rows, $groupColumnKeys, $sumFieldKeys);
        }

        return new Collection($columns, $rows);
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
            }

            //add sum
            foreach ($sumFields as $sumFieldKey => $sumField) {
                if (array_key_exists($sumField, $element)) {

                    $isFirst = false;
                    if (!array_key_exists($sumField, $result[$key])) {
                        $result[$key][$sumField] = $element[$sumField];
                        $isFirst = true;
                    }

                    if (!$isFirst) {
                        if (is_numeric($element[$sumField]) && false === strpos($sumFieldKey, 'as_string')) {
                            $result[$key][$sumField] += $element[$sumField];
                        } else {
                            $result[$key][$sumField] .= $separator . $element[$sumField];
                        }
                    }
                }
            }
        }

        return array_values($result);
    }

    public function getPriority()
    {
        return 0;
    }
}