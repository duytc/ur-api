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
    public function transform(Collection $collection,  array &$metrics, array &$dimensions, $joinBy = null)
    {
        parent::transform($collection, $metrics, $dimensions, $joinBy);

        $rows = $collection->getRows();
        foreach($rows as &$row) {
            try {
                $calculatedValue = $this->language->evaluate($this->expression, ['row' => $row]);
            } catch (\Exception $ex) {
                $calculatedValue = 0;
            }

            $calculatedValue = $calculatedValue ? $calculatedValue : $this->defaultValue;
            $row[$this->fieldName] = $calculatedValue;
        }

        $collection->setRows($rows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }
}