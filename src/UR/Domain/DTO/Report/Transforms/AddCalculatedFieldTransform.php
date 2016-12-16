<?php


namespace UR\Domain\DTO\Report\Transforms;


use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform extends NewFieldTransform implements TransformInterface
{
    const PRIORITY = 3;
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValue';

    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_NAME_KEY, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::TYPE_KEY, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either "field" or "expression" or "type" does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::FIELD_NAME_KEY];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
        $this->type = $addCalculatedField[self::TYPE_KEY];
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed|void
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, $joinBy = null)
    {
        parent::transform($collection, $metrics, $dimensions, $joinBy);
        $expressionForm = $this->convertExpressionForm($this->expression);

        $rows = $collection->getRows();
        foreach ($rows as &$row) {
            try {
                $calculatedValue = $this->language->evaluate($expressionForm, ['row' => $row]);
            } catch (\Exception $ex) {
                $calculatedValue = null;
            }

            $row[$this->fieldName] = $calculatedValue;
        }

        $collection->setRows($rows);
    }

    /**
     * Convert expression from: [fie1d_id]-[field2_id]  to row['field_id'] - row['field2_id']
     * @param $expression
     * @throws \Exception
     * @return mixed
     */
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

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }
}