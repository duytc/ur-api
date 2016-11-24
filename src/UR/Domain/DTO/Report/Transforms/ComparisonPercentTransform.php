<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;
use UR\Util\CalculateRatiosTrait;

class ComparisonPercentTransform extends AbstractTransform implements ComparisonPercentTransformInterface
{
    use CalculateRatiosTrait;
    const PRIORITY = 3;

    const NUMERATOR_KEY = 'numerator';
    const DENOMINATOR_KEY = 'denominator';
    const FIELD_NAME = 'field';

    protected $numerator;
    protected $denominator;
    protected $field;

    /**
     * ComparisonPercentTransform constructor.
     * @param $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::NUMERATOR_KEY, $data) || !array_key_exists(self::DENOMINATOR_KEY, $data) || !array_key_exists(self::FIELD_NAME, $data)) {
            throw new InvalidArgumentException('either "numerator" or "denominator" or "field name" is missing');
        }

        $this->numerator = $data[self::NUMERATOR_KEY];
        $this->denominator = $data[self::DENOMINATOR_KEY];
        $this->field = $data[self::FIELD_NAME];
    }

    /**
     * @return mixed
     */
    public function getNumerator()
    {
        return $this->numerator;
    }

    /**
     * @return mixed
     */
    public function getDenominator()
    {
        return $this->denominator;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    public function transform(Collection $collection, array $metrics, array $dimensions)
    {
        $rows = $collection->getRows();
        foreach ($rows as &$row) {
            $calculatedValue = $this->getPercentage($row[$this->numerator], $row[$this->denominator]);
            $row[$this->field] = $calculatedValue;
        }

        $collection->setRows($rows);
    }
}