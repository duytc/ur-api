<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\ArrayUtilTrait;
use UR\Service\DTO\Collection;

class SortByColumns implements CollectionTransformerInterface, CollectionTransformerJsonConfigInterface
{
    use ArrayUtilTrait;

    const DIRECTION = 'direction';
    const NAMES = 'names';
    const ASC = 'asc';
    const DESC = 'desc';
    protected $ascendingFields;
    protected $descendingFields;

    public function __construct(array $ascendingFields, array $descendingFields)
    {
        $this->ascendingFields = $ascendingFields;
        $this->descendingFields = $descendingFields;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        if ($rows->count() < 1) {
            return $collection;
        }

        $this->array_sort_by_column($rows);

        return new Collection($collection->getColumns(), $rows, $collection->getTypes());
    }

    public function array_sort_by_column(SplDoublyLinkedList $arr)
    {
        $sortCriteria = [];
        foreach ($this->ascendingFields as $ascendingField) {
            $sortCriteria[$ascendingField] = [SORT_ASC];
        }

        foreach ($this->descendingFields as $descendingField) {
            $sortCriteria[$descendingField] = [SORT_DESC];
        }

        $this->multiSort($arr, $sortCriteria, false);
    }

    /**
     * Sort array by multi fields
     * @param $data
     * @param $sortCriteria
     * $sortCriteria = array('field1' => array(SORT_DESC),'field3' => array(SORT_DESC));
     * @param bool $caseInSensitive
     * @return void
     */
    protected function multiSort(SplDoublyLinkedList $data, $sortCriteria, $caseInSensitive = true)
    {
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
            return;
        }

        $rows = $this->getArray($data);
        $args[] = &$rows;
        call_user_func_array('array_multisort', $args);
        $rows = end($args);
        foreach ($rows as $index => $row) {
            $data->offsetSet($index, $row);
        }
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $ascending[self::NAMES] = $this->ascendingFields;
        $ascending[self::DIRECTION] = self::ASC;
        $descending[self::NAMES] = $this->descendingFields;
        $descending[self::DIRECTION] = self::DESC;
        $transformFields[] = $ascending;
        $transformFields[] = $descending;
        return $transformFields;
    }

    /**
     * @return array
     */
    public function getAscendingFields(): array
    {
        return $this->ascendingFields;
    }

    /**
     * @param array $ascendingFields
     */
    public function setAscendingFields(array $ascendingFields)
    {
        $this->ascendingFields = $ascendingFields;
    }

    /**
     * @return array
     */
    public function getDescendingFields(): array
    {
        return $this->descendingFields;
    }

    /**
     * @param array $descendingFields
     */
    public function setDescendingFields(array $descendingFields)
    {
        $this->descendingFields = $descendingFields;
    }
}