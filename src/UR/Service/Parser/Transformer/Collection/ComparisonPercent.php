<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Parser\ReformatDataService;

class ComparisonPercent implements CollectionTransformerInterface, CollectionTransformerJsonConfigInterface
{
    const NUMERATOR_KEY = 'numerator';
    const DENOMINATOR_KEY = 'denominator';

    protected $newColumn;
    protected $numerator;
    protected $denominator;

    private $reformatDataService;

    public function __construct($newColumn, $numerator, $denominator)
    {
        $this->newColumn = $newColumn;
        $this->numerator = $numerator;
        $this->denominator = $denominator;
        $this->reformatDataService = new ReformatDataService();
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
            $columnCheck = array_diff([$this->numerator, $this->denominator], array_keys($row));
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

        if (!in_array($this->newColumn, $collection->getColumns(), true)) {
            $columns[] = $this->newColumn;
//            throw new \InvalidArgumentException('Cannot add calculated column, it already exists');
        }

        $isNumber = array_key_exists($this->newColumn, $types) && $types[$this->newColumn] == FieldType::NUMBER;

        foreach ($rows as &$row) {
            $value = null;
            $numeratorValue = $row[$this->numerator];
            $denominatorValue = $row[$this->denominator];
            $numeratorValue = $this->reformatDataService->reformatData($numeratorValue, FieldType::NUMBER);
            $denominatorValue = $this->reformatDataService->reformatData($denominatorValue, FieldType::NUMBER);
            if (!is_numeric($numeratorValue) || !is_numeric($numeratorValue)) {
                $row[$this->newColumn] = $value;
                continue;
            }

            if ($denominatorValue > 0) {
                $value = abs(($numeratorValue - $denominatorValue) / $denominatorValue);
            }

            $row[$this->newColumn] = $isNumber ? round($value) : $value;
        }

        return new Collection($columns, $rows, $types);
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return mixed
     */
    public function getNewColumn()
    {
        return $this->newColumn;
    }

    /**
     * @param mixed $newColumn
     */
    public function setNewColumn($newColumn)
    {
        $this->newColumn = $newColumn;
    }

    /**
     * @return mixed
     */
    public function getNumerator()
    {
        return $this->numerator;
    }

    /**
     * @param mixed $numerator
     */
    public function setNumerator($numerator)
    {
        $this->numerator = $numerator;
    }

    /**
     * @return mixed
     */
    public function getDenominator()
    {
        return $this->denominator;
    }

    /**
     * @param mixed $denominator
     */
    public function setDenominator($denominator)
    {
        $this->denominator = $denominator;
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->newColumn;
        $transformFields[self::NUMERATOR_KEY] = $this->numerator;
        $transformFields[self::DENOMINATOR_KEY] = $this->denominator;
        return $transformFields;
    }
}