<?php

namespace UR\Service\Parser\Transformer\Collection;


use UR\Service\DataSet\FieldType;

class AddField extends AbstractAddField
{
    const VALUE_KEY = 'value';

    /**
     * @var string
     */
    protected $column;
    protected $value;
    protected $type;


    public function __construct($column, $value = null, $fileType)
    {
        parent::__construct($column);
        $this->value = $value;
        $this->type = $fileType;
    }

    protected function getValue(array $row)
    {
        try {
            if (is_null($this->value)) {
                throw new \Exception(sprintf('Expression can not be null'));
            }

            if (!in_array($this->type, [FieldType::TEXT, FieldType::MULTI_LINE_TEXT])) {
                return $this->value;
            }

            $regex = '/\[(.*?)\]/'; // $fieldsWithBracket = $matches[0];
            if (!preg_match_all($regex, $this->value, $matches)) {
                return $this->value;
            };

            $fields = $matches[1];
            $result = $this->value;

            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $replaceValue = $row[$field];
                $result = str_replace(sprintf('[%s]', $field), $replaceValue, $result);
            }
        } catch (\Exception $exception) {
            $result = null;
        }

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_ADD_FIELD;
    }

    /**
     * @return mixed
     */
    public function getTransformValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setTransformValue($value)
    {
        $this->value = $value;
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
}