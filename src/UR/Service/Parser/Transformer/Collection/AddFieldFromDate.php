<?php

namespace UR\Service\Parser\Transformer\Collection;


use UR\Service\PublicSimpleException;

class AddFieldFromDate extends AbstractAddField implements CollectionTransformerJsonConfigInterface
{
    const FIELD_FROM = 'fromField';
    const FIELD_NAME = 'fieldName';
    const FORMAT = 'format';

    /**
     * @var string
     */
    protected $column;
    protected $fromField;
    protected $format;
    protected $type;


    public function __construct($column, $fromField, $format, $fieldType)
    {
        parent::__construct($column);
        $this->fromField = $fromField;
        $this->format = $format;
        $this->type = $fieldType;
    }

    /**
     * @return mixed
     */
    public function getTransformValue()
    {
        return $this->format;
    }

    /**
     * @param mixed $format
     */
    public function setTransformValue($format)
    {
        $this->format = $format;
    }

    /**
     * @return string|null
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param string $column
     */
    public function setColumn(string $column)
    {
        $this->column = $column;
    }

    /**
     * @return string|null
     */
    public function getFromField()
    {
        return $this->fromField;
    }

    /**
     * @param string $fromField
     */
    public function setFromField(string $fromField)
    {
        $this->fromField = $fromField;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
    public function validate()
    {
        // TODO: Implement validate() method.
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_NAME] = $this->column;
        $transformFields[self::FIELD_FROM] = $this->fromField;
        $transformFields[self::FORMAT] = $this->format;
        return $transformFields;
    }

    /**
     * @param array $row
     * @return mixed
     * @throws PublicSimpleException
     */
    protected function getValue(array $row)
    {
        if (empty($this->format)) {
            throw new PublicSimpleException(sprintf('Format can not be null'));
        }

        if (empty($this->fromField) || !array_key_exists($this->fromField, $row) || empty($row[$this->fromField])) {
            return null;
        }

        $result = null;
        try {
            $valueFromField = new \DateTime($row[$this->fromField]);
            if ($valueFromField instanceof \DateTime) {
                $result = $valueFromField->format($this->format);
            }
        } catch (\Exception $exception) {

        }

        return $result;
    }
}