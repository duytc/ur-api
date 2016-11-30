<?php


namespace UR\Domain\DTO\Report\Formats;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class NumberFormat extends AbstractFormat implements NumberFormatInterface
{
    const PRECISION_KEY = 'decimals';
    const THOUSAND_SEPARATOR_KEY = 'thousandsSeparator';

    const DEFAULT_DECIMAL_SEPARATOR = '.';
    const DEFAULT_THOUSAND_SEPARATOR = ',';
    const DEFAULT_PRECISION = 3;

    /** @var int */
    protected $precision;

    /** @var string */
    protected $thousandSeparator;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::PRECISION_KEY, $data) || !array_key_exists(self::THOUSAND_SEPARATOR_KEY, $data)) {
            throw new InvalidArgumentException('either "decimals" or "thousandsSeparator" is missing');
        }

        $this->precision = empty($data[self::PRECISION_KEY]) ? self::DEFAULT_PRECISION : $data[self::PRECISION_KEY];
        $this->thousandSeparator = empty($data[self::THOUSAND_SEPARATOR_KEY]) ? self::DEFAULT_THOUSAND_SEPARATOR : $data[self::THOUSAND_SEPARATOR_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @inheritdoc
     */
    public function getThousandSeparator()
    {
        return $this->thousandSeparator;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_NUMBER;
    }

    /**
     * @inheritdoc
     */
    public function format(Collection $collection, array $metrics, array $dimensions)
    {
        $rows = $collection->getRows();
        $newRows = [];
        $fields = $this->getFields();

        $decimalSeparator = !strcmp($this->thousandSeparator, self::DEFAULT_THOUSAND_SEPARATOR) ? self::DEFAULT_DECIMAL_SEPARATOR : self::DEFAULT_THOUSAND_SEPARATOR;

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $row[$field] = number_format($row[$field], $this->precision, $decimalSeparator, $this->thousandSeparator);
            }

            $newRows[] = $row;
        }

        $collection->setRows($newRows);

        return $collection;
    }
}