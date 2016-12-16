<?php

namespace UR\Service\Parser\Transformer\Collection;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\Alert\ProcessAlert;

class AddCalculatedField extends AbstractAddField
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

    public function __construct(ExpressionLanguage $language, $column, $expression, $defaultValue = 0)
    {
        $this->column = $column;
        $this->expression = $expression;
        $this->defaultValue = $defaultValue;
        $this->language = $language;
    }

    protected function getValue(array $row)
    {
        try {
            $this->language->register('abs', function ($number) {
                return sprintf('(is_numeric(%1$s) ? abs(%1$s) : %1$s)', $number);
            }, function ($arguments, $number) {
                if (!is_numeric($number)) {
                    return $number;
                }

                return abs($number);
            });

            $expressionForm = $this->convertExpressionForm($this->expression);
            $result = $this->language->evaluate($expressionForm, ['row' => $row]);
        } catch (\Exception $exception) {
            $result = null;
        }

        if ($result === false) {
            return null;
        }

        return $result;
    }

    protected function convertExpressionForm($expression)
    {
        if (is_null($expression)) {
            throw new \Exception(sprintf('Expression for calculated field can not be null'));
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fieldsInBracket = $matches[0];
        $fields = $matches[1];
        $newExpressionForm = null;

        foreach ($fields as $index => $field) {
            $replaceString = sprintf('row[\'%s\']', $field);
            $expression = str_replace($fieldsInBracket[$index], $replaceString, $expression);
        }

        return $expression;
    }
}