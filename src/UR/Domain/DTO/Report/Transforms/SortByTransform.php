<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class SortByTransform extends AbstractTransform implements SortByTransformInterface
{
    const PRIORITY = 4;
    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    const FIELDS_KEY = 'names';
    const SORT_DIRECTION_KEY = 'direction';

    /**
     * @var array
     */
    protected $ascSorts;

    /**
     * @var array
     */
    protected $descSorts;

    protected $ascFirst;

    protected $sortObjects;

    function __construct(array $sortObjects)
    {
        parent::__construct();

        $this->ascSorts = [];
        $this->descSorts = [];
        $this->ascFirst = true;
        foreach ($sortObjects as $sortObject) {

            if (!array_key_exists(self::FIELDS_KEY, $sortObject) || !array_key_exists(self::SORT_DIRECTION_KEY, $sortObject)) {
                throw new InvalidArgumentException('either "fields" or "direction" is missing');

            }

            $this->sortObjects[] = $sortObject;
        }

        if (count($this->sortObjects) !== 2) {
            throw new InvalidArgumentException('only "asc" and "desc" sort is supported');
        }

        $intersect = array_intersect($this->sortObjects[0][self::FIELDS_KEY], $this->sortObjects[1][self::FIELDS_KEY]);
        if (count($intersect) > 0) {
            throw new InvalidArgumentException(sprintf('"%s" are present in both sort direction', implode(',', $intersect)));
        }
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
        $excludeFields= [];
        $rows = $collection->getRows();
        $params = [];
        // collect column data
        foreach($rows as $row) {
            foreach($this->sortObjects as $sortObject) {
                foreach($sortObject[self::FIELDS_KEY] as $field) {
                    if (!array_key_exists($field, $row)) {
                        $excludeFields[] = $field;
                        break;
                    }
                    ${$field . "values"}[] = $row[$field];
                }
            }
        }

        // build param
        foreach($this->sortObjects as $sortObject) {
            foreach($sortObject[self::FIELDS_KEY] as $field) {
                if (in_array($field, $excludeFields)) {
                    break;
                }
                $params[] = ${$field . "values"};
                if ($sortObject[self::SORT_DIRECTION_KEY] === self::SORT_ASC) {
                    $params[] = SORT_ASC;
                } else {
                    $params[] = SORT_DESC;
                }
            }
        }

        $params[] = &$rows;

        call_user_func_array('array_multisort', $params);
        $collection->setRows($rows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        // nothing changed in metrics and dimensions
    }


    /**
     * @inheritdoc
     */
    protected function sortByFields(array $sortFields, Collection $reports, array $metrics = null, array $dimensions = null)
    {
        $rows = $reports->getRows();

        $sortCriteria = [];
        foreach ($sortFields as $field) {
            $sortCriteria[$field] = [$this->direction[$field], SORT_REGULAR];
        }

        $reports = $this->multiSort($rows, $sortCriteria, false);
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

    /**
     * @return array
     */
    public function getAscSorts()
    {
        return $this->ascSorts;
    }

    /**
     * @return array
     */
    public function getDescSorts()
    {
        return $this->descSorts;
    }
}