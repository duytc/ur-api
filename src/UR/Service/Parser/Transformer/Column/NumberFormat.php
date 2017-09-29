<?php

namespace UR\Service\Parser\Transformer\Column;

use \Exception;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class NumberFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const SEPARATOR_COMMA = ',';
    const SEPARATOR_NONE = 'none';
    const DECIMALS = 'decimals';
    const THOUSANDS_SEPARATOR = 'thousandsSeparator';

    private static $supportedThousandsSeparator = [
        ",", "none"
    ];

    /**
     * @var int
     */
    protected $decimals;
    /**
     * @var string
     */
    protected $thousandsSeparator;

    public function __construct($field, $decimals, $thousandsSeparator)
    {
        parent::__construct($field);
        $this->decimals = $decimals;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    /**
     * @inheritdoc
     */
    public function transform($value)
    {
        if (!is_numeric($value)) {
            return null; // return null on non numeric value
        }

        $thousandsSeparator = $this->thousandsSeparator === self::SEPARATOR_NONE ? '' : $this->thousandsSeparator;

        return number_format($value, $this->decimals, '.', $thousandsSeparator);
    }

    /**
     * @inheritdoc
     */
    public function validate()
    {
        if (!is_numeric($this->decimals)) {
            throw new Exception(sprintf('Error at field "%s": Decimals must be number', $this->getField()));
        }

        if (!in_array($this->thousandsSeparator, self::$supportedThousandsSeparator)) {
            throw new Exception(sprintf('Error at field "%s": thousands separator mus be one of %s ', $this->getField(), implode(", ", self::$supportedThousandsSeparator)));
        }
    }

    /**
     * @inheritdoc
     */
    public function transformCollection(Collection $collection, ConnectedDataSourceInterface $connectedDataSource) {
        $rows = $collection->getRows();

        if (!$rows instanceof SplDoublyLinkedList || $rows->count() < 1) {
            return $collection;
        }

        $mapFields = array_flip($connectedDataSource->getMapFields());
        $dateFieldInDataSet = $this->getField();

        if (!array_key_exists($dateFieldInDataSet, $mapFields)) {
            return $collection;
        }

        $dateFieldInFile = $mapFields[$dateFieldInDataSet];
        $newRows = new SplDoublyLinkedList();

        foreach ($rows as $row) {
            if (!array_key_exists($dateFieldInFile, $row)) {
                continue;
            }

            $row[$dateFieldInFile] = $this->transform($row[$dateFieldInFile]);
            $newRows->push($row);
        }

        $collection->setRows($newRows);

        return $collection;
    }
}