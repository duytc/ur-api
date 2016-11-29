<?php


namespace UR\Service\DTO\Report;


use UR\Exception\InvalidArgumentException;

class WeightedCalculation implements WeightedCalculationInterface
{
    const CALCULATING_FIELD_KEY = 'field';
    const FREQUENCY_FIELD_KEY = 'frequency';
    const WEIGHTED_FIELD_KEY = 'weight';

    /**
     * @var array
     */
    protected $fields;

    /**
     * @var array
     */
    protected $frequencies;

    /**
     * @var array
     */
    protected $weighted;

    /**
     * WeightedCalculation constructor.
     *
     * @param $calculations
     */
    public function __construct(array $calculations)
    {
        $this->fields = [];
        $this->frequencies = [];
        $this->weighted = [];

        foreach($calculations as $calculation) {
            if (!is_array($calculation)) {
                throw new InvalidArgumentException(sprintf('expect "calculations" is an array of array, %s given', gettype($calculations)));
            }

            $this->addCalculation($calculation);
        }
    }

    /**
     * @param $field
     * @return boolean
     */
    public function hasCalculatingField($field)
    {
        return in_array($field, $this->fields);
    }

    /**
     * @return bool
     */
    public function hasCalculation()
    {
        return !empty($this->fields);
    }

    /**
     * @param $calculatingField
     * @return string|null
     */
    public function getFrequencyField($calculatingField)
    {
        if ($this->hasCalculatingField($calculatingField)) {
            return $this->frequencies[$calculatingField];
        }

        return null;
    }

    /**
     * @param $calculatingField
     * @return string|null
     */
    public function getWeightedField($calculatingField)
    {
        if ($this->hasCalculatingField($calculatingField)) {
            return $this->weighted[$calculatingField];
        }

        return null;
    }

    /**
     * @param array $calculation
     * @return self
     */
    protected function addCalculation(array $calculation)
    {
        if (!array_key_exists(self::CALCULATING_FIELD_KEY, $calculation) || !array_key_exists(self::FREQUENCY_FIELD_KEY, $calculation) || !array_key_exists(self::WEIGHTED_FIELD_KEY, $calculation)) {
            throw new InvalidArgumentException('either "field" or "frequency" or "weighted" is missing');
        }

        $this->fields[] = $calculation[self::CALCULATING_FIELD_KEY];
        $this->frequencies[$calculation[self::CALCULATING_FIELD_KEY]] = $calculation[self::FREQUENCY_FIELD_KEY];
        $this->weighted[$calculation[self::CALCULATING_FIELD_KEY]] = $calculation[self::WEIGHTED_FIELD_KEY];
    }
}