<?php

namespace UR\Domain\DTO\Report\Transforms;

use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;
use UR\Util\CalculateRatiosTrait;

class ComparisonPercentTransform extends NewFieldTransform implements TransformInterface
{
    use CalculateRatiosTrait;

    const TRANSFORMS_TYPE = 'comparisonPercent';
    const NUMERATOR_KEY = 'numerator';
    const DENOMINATOR_KEY = 'denominator';

    protected $numerator;
    protected $denominator;

    /**
     * ComparisonPercentTransform constructor.
     * @param $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::NUMERATOR_KEY, $data) || !array_key_exists(self::DENOMINATOR_KEY, $data) ||
            !array_key_exists(self::FIELD_NAME_KEY, $data) || !array_key_exists(self::TYPE_KEY, $data)
        ) {
            throw new InvalidArgumentException('either "numerator" or "denominator" or "field name" or "type" is missing');
        }

        $this->numerator = $data[self::NUMERATOR_KEY];
        $this->denominator = $data[self::DENOMINATOR_KEY];
        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->type = $data[self::TYPE_KEY];
    }

    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        parent::transform($collection, $metrics, $dimensions, $outputJoinField);

        $rows = $collection->getRows();
        foreach ($rows as &$row) {
            if (!array_key_exists($this->numerator, $row) || !array_key_exists($this->denominator, $row)) {
                $row[$this->fieldName] = null;
                continue;
            }
            $calculatedValue = $this->getPercentage($row[$this->numerator], $row[$this->denominator]);
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