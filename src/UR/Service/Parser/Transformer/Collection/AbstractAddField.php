<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
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

        if (in_array($this->column, $columns, true)) {
            return $collection;
        }

        $columns[] = $this->column;

        foreach ($rows as $idx => &$row) {
            $value = $this->getValue($row);
            if (is_array($value)) {
                return $value;
            }
            $row[$this->column] = $value;
        }

        return new Collection($columns, $rows, $types);
    }

    /**
     * @param array $row
     * @return mixed
     */
    abstract protected function getValue(array $row);
}