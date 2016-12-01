<?php


namespace UR\Domain\DTO\Report\Transforms;


use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform extends AbstractTransform implements AddCalculatedFieldTransformInterface
{
    const PRIORITY = 3;
    const NAME_CALCULATED_FIELD = 'field';
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValue';

    /**
     * @var string
     */
    protected $fieldName;
    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField)
    {
        parent::__construct();
        if (!array_key_exists(self::NAME_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
//            || !array_key_exists(self::DEFAULT_VALUE_CALCULATED_FIELD, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either name or expression or default value does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::NAME_CALCULATED_FIELD];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
//        $this->defaultValue = $addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD];

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
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
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
        if (!in_array($this->fieldName, $columns)) {
            $columns[] = $this->fieldName;
            $collection->setColumns($columns);
            $metrics[] = $this->fieldName;
        }
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (array_key_exists($this->fieldName, $metrics) || array_key_exists($this->fieldName, $dimensions)) {
            return;
        }

        $dimensions[$this->fieldName] = ucwords(str_replace("_", " ", $this->fieldName));
    }


    /**
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }
}