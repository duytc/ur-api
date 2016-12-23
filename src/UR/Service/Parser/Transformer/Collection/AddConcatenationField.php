<?php

namespace UR\Service\Parser\Transformer\Collection;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class AddConcatenationField extends AbstractAddField
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

        $fields = $matches[1]; // $fieldsWithBracket = $matches[0];
        $newExpression = '';

        $convertedFields = array_map(function ($field) {
            // TODO: check if fieldsInBracket existed in detected fields of connected data source
            return sprintf('row[\'%s\']', $field);
        }, $fields);

        if (is_array($convertedFields) && count($convertedFields) > 0) {
            $newExpression = implode('~', $convertedFields);
        }

        return $newExpression;
    }
}