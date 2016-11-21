<?php


namespace UR\Service\Report\Sorter;


use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;
use UR\Service\DTO\Collection;

class SortBy implements SortByInterface
{
    /**
     * @inheritdoc
     */
    public function sortByFields(array $sortFields, Collection $reports, array $metrics, array $dimensions)
    {
        $rows = $reports->getRows();

        $sortCriteria = [];
        /** @var SortByTransformInterface[] $sortFields */
        foreach ($sortFields as $sortField) {
            foreach ($sortField->getFields() as $field) {
                $sortCriteria[$field] = [$sortField->getDirection(), SORT_STRING];
            }
        }

        $newRows = $this->multiSort($rows, $sortCriteria, false);
        $reports->setRows($newRows);

        return $reports;
    }

    /**
     * Sort array by multi fields
     * @param $data
     * @param $sortCriteria
     * $sortCriteria = array('field1' => array(SORT_DESC, SORT_NUMERIC),'field3' => array(SORT_DESC, SORT_NUMERIC));
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