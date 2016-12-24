<?php

namespace UR\Service\Parser\Transformer\Collection;


class AddConcatenatedField extends AbstractAddField
{
    /**
     * @var string
     */
    protected $column;
    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct($column, $expression, $defaultValue = 0)
    {
        $this->column = $column;
        $this->expression = $expression;
        $this->defaultValue = $defaultValue;
    }

    protected function getValue(array $row)
    {
        try {
            if (is_null($this->expression)) {
                throw new \Exception(sprintf('Expression for calculated field can not be null'));
            }

            $regex = '/\[(.*?)\]/'; // $fieldsWithBracket = $matches[0];
            if (!preg_match_all($regex, $this->expression, $matches)) {
                return $this->expression;
            };

            $fields = $matches[1];
            $result = $this->expression;

            foreach ($fields as $field) {
                $replaceValue = array_key_exists($field, $row) ? $row[$field] : '';
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
    public function getPriority()
    {
        return self::TRANSFORM_PRIORITY_ADD_CONCATENATION_FIELD;
    }
}