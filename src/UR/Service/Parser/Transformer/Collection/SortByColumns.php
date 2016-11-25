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
        $columns = $collection->getColumns();
        $rows = $collection->getRows();

        for ($i = 0; $i < count($this->sortByColumns); $i++) {
            for ($j = 0; $j < count($this->sortByColumns[$i]); $j++) {

                $sortByColumns = array_intersect($columns, $this->sortByColumns[$i][$j][TransformType::NAMES]);

                if (count($sortByColumns) != count($this->sortByColumns[$i][$j][TransformType::NAMES])) {
                    return new Collection($columns, $rows);
//            throw new \InvalidArgumentException('Cannot sort the collection, some of the columns do not exist');
                }
                // todo implement sorting

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

        return new Collection($columns, $rows);
    }

    public function getPriority()
    {
        return 0;
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
            $colList = array();
            foreach ($data as $key => $row) {
                $convertToLower = $caseInSensitive && (in_array(SORT_STRING, $sortAttributes) || in_array(SORT_REGULAR, $sortAttributes));
                $rowData = $convertToLower ? strtolower($row[$sortColumn]) : $row[$sortColumn];
                $colLists[$sortColumn][$key] = $rowData;
            }
            $args[] = &$colLists[$sortColumn];

            foreach ($sortAttributes as $sortAttribute) {
                $tmp[$i] = $sortAttribute;
                $args[] = &$tmp[$i];
                $i++;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return end($args);
    }
}