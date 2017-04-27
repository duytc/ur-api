<?php

namespace UR\Service\DTO\DataImportTable;


use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;

class ColumnIndex
{
    /** @var string */
    private $columnName;
    /** @var string */
    private $columnType;
    /** @var int */
    private $columnLength;

    /**
     * ColumnIndex constructor. columnLength is auto detected if not set
     *
     * @param string $columnName
     * @param string $columnType FieldType::LARGE_TEXT, FieldType::TEXT, FieldType::NUMBER, ...
     * @param int|null $columnLength
     */
    public function __construct($columnName, $columnType, $columnLength = null)
    {
        $this->columnName = $columnName;
        $this->columnType = $columnType;

        // auto detect columnLength if not yet defined
        if (null === $columnLength) {
            // set field length if text of longtext
            $columnLength = $columnType === FieldType::LARGE_TEXT
                ? Synchronizer::FIELD_LENGTH_LARGE_TEXT
                : ($columnType === FieldType::TEXT
                    ? Synchronizer::FIELD_LENGTH_TEXT
                    : null // other types: not set length
                );
        }

        $this->columnLength = $columnLength;
    }

    /**
     * @return string
     */
    public function getColumnName()
    {
        return $this->columnName;
    }

    /**
     * @return string
     */
    public function getColumnType()
    {
        return $this->columnType;
    }

    /**
     * @return int
     */
    public function getColumnLength()
    {
        return $this->columnLength;
    }

    /**
     * manually set columnLength
     * 
     * @param int $columnLength
     * @return $this
     */
    public function setColumnLength($columnLength)
    {
        $this->columnLength = $columnLength;
        return $this;
    }
}