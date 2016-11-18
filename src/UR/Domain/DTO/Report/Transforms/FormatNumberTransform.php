<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class FormatNumberTransform implements FormatNumberTransformInterface
{
    const DEFAULT_DECIMAL_SEPARATOR = '.';
    const DEFAULT_THOUSAND_SEPARATOR = ',';
    const DEFAULT_PRECISION = 3;

    const FIELD_NAME_KEY = 'field';
    const PRECISION_KEY = 'precision';
    const THOUSAND_SEPARATOR_KEY = 'thousandSeparator';

    protected $precision;

    protected $thousandSeparator;

    protected $fieldName;

    function __construct(array $data)
    {
        if (!array_key_exists(self::FIELD_NAME_KEY, $data)) {
            throw new InvalidArgumentException('"field name" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];

        $this->precision = array_key_exists(self::PRECISION_KEY, $data) ? $data[self::PRECISION_KEY] : self::DEFAULT_PRECISION;
        $this->thousandSeparator = array_key_exists(self::THOUSAND_SEPARATOR_KEY, $data) ? $data[self::THOUSAND_SEPARATOR_KEY] : self::DEFAULT_THOUSAND_SEPARATOR;
    }

    /**
     * @return mixed
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @return mixed
     */
    public function getThousandSeparator()
    {
        return $this->thousandSeparator;
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        foreach($rows as $row) {
            if (!array_key_exists($this->getFieldName(), $row)) {
                continue;
            }

            $row[$this->getFieldName()] = number_format($row[$this->getFieldName()], $this->precision, self::DEFAULT_DECIMAL_SEPARATOR, $this->thousandSeparator);
        }
    }
}