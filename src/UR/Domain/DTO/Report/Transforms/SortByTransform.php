<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class SortByTransform extends AbstractTransform implements SortByTransformInterface
{
    const PRIORITY = 4;
    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    const SORT_DIRECTION_ASC = SORT_ASC;
    const SORT_DIRECTION_DESC = SORT_DESC; //Importance: not change to use in array_multisort when sorting report

    const DEFAULT_SORT_DIRECTION = 'asc';
    const FIELDS_KEY = 'names';
    const SORT_DIRECTION_KEY = 'direction';

    /**
     * @var array
     */
    protected $fields;

    protected $direction;

    protected $sortDirection;

    function __construct(array $data)
    {
        parent::__construct();
        if (!array_key_exists(self::FIELDS_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        $this->fields = $data[self::FIELDS_KEY];

        $this->direction = array_key_exists(self::SORT_DIRECTION_KEY, $data) ? $data[self::SORT_DIRECTION_KEY] : self::DEFAULT_SORT_DIRECTION;

        if ($this->direction === self::SORT_ASC) {
            $this->direction = self::SORT_DIRECTION_ASC;
        } else {
            $this->direction = self::SORT_DIRECTION_DESC;
        }
    }

    /**
     * @return mixed
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function transform(Collection $collection, array $metrics, array $dimensions)
    {
       $results =  $this->sortByFields($this->getFields(), $collection, $metrics, $dimensions);
       $collection->setRows($results);

        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function sortByFields(array $sortFields, Collection $reports, array $metrics = null, array $dimensions = null)
    {
        $rows = $reports->getRows();

        $sortCriteria = [];
        foreach ($sortFields as $field) {
            $sortCriteria[$field] = [$this->getDirection(), SORT_STRING];
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
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }
}