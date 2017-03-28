<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class ComparisonPercent implements CollectionTransformerInterface
{
    const NUMERATOR_KEY = 'numerator';
    const DENOMINATOR_KEY = 'denominator';
    
    protected $newColumn;
    protected $columnA;
    protected $columnB;

    public function __construct($newColumn, $columnA, $columnB)
    {
        $this->newColumn = $newColumn;
        $this->columnA = $columnA;
        $this->columnB = $columnB;
    }

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null)
    {
        $rows = $collection->getRows();
        $types = $collection->getTypes();

        if (count($rows) < 1) {
            return $collection;
        }

        $columns = $collection->getColumns();
        $columnCheck = [];
        foreach ($rows as $row) {
            $columnCheck = array_diff([$this->columnA, $this->columnB], array_keys($row));
            break;
        }

        if (count($columnCheck) > 0) {
            $columns[] = $this->newColumn;
            foreach ($rows as $idx => &$row) {
                $value = null;
                $row[$this->newColumn] = $value;
            }

            return new Collection($columns, $rows, $types);
        }

        if (in_array($this->newColumn, $collection->getColumns(), true)) {
            throw new \InvalidArgumentException('Cannot add calculated column, it already exists');
        }

        $columns[] = $this->newColumn;

        foreach ($rows as &$row) {
            $value = null;
            if (!is_numeric($row[$this->columnA]) || !is_numeric($row[$this->columnB])) {
                $row[$this->newColumn] = $value;
                continue;
            }

            if ($row[$this->columnB] > 0) {
                $value = abs(($row[$this->columnA] - $row[$this->columnB]) / $row[$this->columnB]);
            }

            $row[$this->newColumn] = $value;
        }

        return new Collection($columns, $rows, $types);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_COMPARISON_PERCENT;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }
}