<?php

namespace UR\Service\AutoOptimization\DTO;

use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\Collection\AddField;

class IdentifierGenerator implements IdentifierGeneratorInterface
{
    /** @var  AddField */
    private $addField;

    /** @var string */
    private $regression;

    /**
     * IdentifierGenerator constructor.
     * @param string $regression
     */
    public function __construct($regression)
    {
        $this->regression = $regression;
    }


    /**
     * @param Collection $collection
     * @return Collection
     */
    public function generateIdentifiers(Collection $collection)
    {
        $collection = $this->getAddField()->transform($collection);

        $collection = $this->updateColumnsAndTypes($collection);

        return $collection;
    }

    /**
     * @return AddField
     */
    public function getAddField()
    {
        if (!$this->addField instanceof AddField) {
            $this->addField = new AddField(AutoOptimizationConfigInterface::IDENTIFIER_COLUMN, $this->getRegression(), FieldType::TEXT);
        }

        return $this->addField;
    }

    /**
     * @return string
     */
    public function getRegression()
    {
        return $this->regression;
    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    private function updateColumnsAndTypes(Collection $collection)
    {
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!array_key_exists(AutoOptimizationConfigInterface::IDENTIFIER_COLUMN, $columns)) {
            $columns[AutoOptimizationConfigInterface::IDENTIFIER_COLUMN] = AutoOptimizationConfigInterface::IDENTIFIER_COLUMN;
        }

        if (!array_key_exists(AutoOptimizationConfigInterface::IDENTIFIER_COLUMN, $types)) {
            $types[AutoOptimizationConfigInterface::IDENTIFIER_COLUMN] = FieldType::TEXT;
        }

        $collection->setColumns($columns);
        $collection->setTypes($types);

        return $collection;
    }
}