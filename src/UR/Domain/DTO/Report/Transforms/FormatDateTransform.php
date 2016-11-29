<?php


namespace UR\Domain\DTO\Report\Transforms;


use DateTime;
use Symfony\Component\Validator\Constraints\Date;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class FormatDateTransform extends AbstractTransform implements FormatDateTransformInterface
{
    const PRIORITY = 4;
    const FROM_FORMAT_KEY = 'from';
    const TO_FORMAT_KEY = 'to';
    const FIELD_NAME_KEY = 'field';

    protected $fromFormat;

    protected $toFormat;

    protected $fieldName;

    function __construct(array $data)
    {
        parent::__construct();
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

            $date = new DateTime($row[$this->getFieldName()]);
            if (!$date instanceof DateTime) {
                throw new \Exception(sprintf('System can not create date from format: %s', $this->fromFormat));
            }
            $row[$this->getFieldName()] = $date->format($this->toFormat);
            $newRows[] = $row;
        }

        $collection->setRows($newRows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        // nothing changed in metrics and dimensions
    }


    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }
}