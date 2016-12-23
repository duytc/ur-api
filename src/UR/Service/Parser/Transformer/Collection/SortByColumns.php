<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DataSet\TransformType;
use UR\Service\DTO\Collection;

class SortByColumns implements CollectionTransformerInterface
{
    protected $sortByColumns;

    public function __construct(array $sortByColumns)
    {
        $this->sortByColumns = $sortByColumns;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        if (count($rows) < 1) {
            return $collection;
        }

        for ($i = 0; $i < count($this->sortByColumns); $i++) {
            for ($j = 0; $j < count($this->sortByColumns[$i]); $j++) {

                switch ($this->sortByColumns[$i][$j][TransformType::DIRECTION]) {
                    case 'asc':
                        $this->sortByColumns[$i][$j][TransformType::DIRECTION] = SORT_ASC;
                        break;
                    case 'desc':
                        $this->sortByColumns[$i][$j][TransformType::DIRECTION] = SORT_DESC;
                        break;
                }
            }
        }

        $rows = $this->array_sort_by_column($rows, $this->sortByColumns);

        return new Collection($collection->getColumns(), $rows);
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::TRANSFORM_PRIORITY_SORT;
    }

    public function array_sort_by_column(&$arr, $cols)
    {
        $sort_name = array();
        $sort_direction = array();

        for ($i = 0; $i < count($cols); $i++) {
            for ($j = 0; $j < count($cols[$i]); $j++) {
                foreach ($cols[$i][$j][TransformType::NAMES] as $name) {
                    $sort_direction[] = $cols[$i][$j][TransformType::DIRECTION];
                    $sort_name[] = $name;
                }
            }
        }

        $params = array();
        $params[] = $arr;
        $sortCriteria = array();
        for ($i = 0; $i < count($sort_name); $i++) {
            $params[] = $sort_name[$i];
            $params[] = $sort_direction[$i];
            $sortCriteria[$sort_name[$i]][] = $sort_direction[$i];
        }

        return $this->multiSort($arr, $sortCriteria, false);
    }

    /**
     * Sort array by multi fields
     * @param $data
     * @param $sortCriteria
     * $sortCriteria = array('field1' => array(SORT_DESC),'field3' => array(SORT_DESC));
     * @param bool $caseInSensitive
     * @return mixed
     */
    protected function multiSort($data, $sortCriteria, $caseInSensitive = true)
    {
        if (!is_array($data) || !is_array($sortCriteria))
            return false;
        $args = array();
        $i = 0;
        foreach ($sortCriteria as $sortColumn => $sortAttributes) {
            $colLists = array();

            foreach ($data as $key => $row) {
                if (!array_key_exists($sortColumn, $row)) {
                    continue;
                }

                $convertToLower = $caseInSensitive && (in_array(SORT_STRING, $sortAttributes) || in_array(SORT_REGULAR, $sortAttributes));
                $rowData = $convertToLower ? strtolower($row[$sortColumn]) : $row[$sortColumn];
                $colLists[$sortColumn][$key] = $rowData;
            }
            if (count($colLists) < 1) {
                continue;
            }

            $args[] = &$colLists[$sortColumn];

            foreach ($sortAttributes as $sortAttribute) {
                $tmp[$i] = $sortAttribute;
                $args[] = &$tmp[$i];
                $i++;
            }
        }

        if (count($args) < 1) {
            return $data;
        }

        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return end($args);
    }
}