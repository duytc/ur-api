<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
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

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null)
    {
        $rows = $collection->getRows();
        if (count($rows) < 1) {
            return $collection;
        }
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!in_array($this->column, $columns, true)) {
            $columns[] = $this->column;
        }

        $isNumber = array_key_exists($this->column, $types) && $types[$this->column] == FieldType::NUMBER;
        foreach ($rows as $idx => &$row) {
            $value = $this->getValue($row);

            // json does not support NAN or INF values
            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                $value = null;
            }

            if (is_array($value)) {
                return $value;
            }

            $row[$this->column] = ($isNumber && $value !== null) ? round($value) : $value;
        }

        return new Collection($columns, $rows, $types);
    }

    /**
     * @param array $row
     * @return mixed
     */
    abstract protected function getValue(array $row);
}