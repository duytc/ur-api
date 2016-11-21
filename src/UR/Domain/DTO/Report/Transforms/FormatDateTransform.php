<?php


namespace UR\Domain\DTO\Report\Transforms;


use DateTime;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class FormatDateTransform implements FormatDateTransformInterface
{
    const FROM_FORMAT_KEY = 'from';
    const TO_FORMAT_KEY = 'to';
    const FIELD_NAME_KEY = 'field';

    protected $fromFormat;

    protected $toFormat;

    protected $fieldName;

    function __construct(array $data)
    {
        if (!array_key_exists(self::TO_FORMAT_KEY, $data) || !array_key_exists(self::FROM_FORMAT_KEY, $data) || !array_key_exists(self::FIELD_NAME_KEY, $data)) {
            throw new InvalidArgumentException('either "from" or "to" or "field" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->fromFormat = $data[self::FROM_FORMAT_KEY];
        $this->toFormat = $data[self::TO_FORMAT_KEY];
    }

    /**
     * @return mixed
     */
    public function getFromFormat()
    {
        return $this->fromFormat;
    }

    /**
     * @return mixed
     */
    public function getToFormat()
    {
        return $this->toFormat;
    }

    public function transform(Collection $collection,  array $metrics, array $dimensions)
    {
        $rows = $collection->getRows();
        $newRows = [];
        foreach ($rows as $row) {
            if (!array_key_exists($this->getFieldName(), $row)) {
                continue;
            }

            $date = DateTime::createFromFormat($this->fromFormat, $row[$this->getFieldName()]);
            $row[$this->getFieldName()] = $date->format($this->toFormat);
            $newRows[] = $row;
        }

        $collection->setRows($newRows);
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }
}