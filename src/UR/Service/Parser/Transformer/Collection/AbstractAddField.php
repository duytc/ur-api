<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;

abstract class AbstractAddField implements CollectionTransformerInterface
{
    /**
     * @var string
     */
    protected $column;

    /**
     * AbstractAddField constructor.
     * @param string $column
     */
    public function __construct($column)
    {
        $this->column = $column;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        if ($rows->count() < 1) {
            return $collection;
        }
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!in_array($this->column, $columns, true)) {
            $columns[] = $this->column;
        }

        $isNumber = array_key_exists($this->column, $types) && $types[$this->column] == FieldType::NUMBER;
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $index => $row) {
            $value = $this->getValue($row);

            // json does not support NAN or INF values
            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                $value = null;
            }

            if (is_array($value)) {
                return $value;
            }

            $row[$this->column] = ($isNumber && $value !== null) ? round($value) : $value;
            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);

        return new Collection($columns, $newRows, $types);
    }

    /**
     * @param array $row
     * @return mixed
     */
    abstract protected function getValue(array $row);
}