<?php


namespace UR\Domain\DTO\Report\Formats;


use DateTime;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class DateFormat extends AbstractFormat implements DateFormatInterface
{
    const OUTPUT_FORMAT_KEY = 'format';

    /** @var string */
    protected $outputFormat;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::OUTPUT_FORMAT_KEY, $data)) {
            throw new InvalidArgumentException('"format" is missing');
        }

        $this->outputFormat = $data[self::OUTPUT_FORMAT_KEY];
    }

    /**
     * @return mixed
     */
    public function getOutputFormat()
    {
        return $this->outputFormat;
    }

    /**
     * @inheritdoc
     */
    public function format(Collection $collection, array $metrics, array $dimensions)
    {
        $rows = $collection->getRows();
        $newRows = [];
        $fields = $this->getFields();

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $date = date_create_from_format('Y-m-d', $row[$field]);
                if (!$date instanceof DateTime) {
                    throw new \Exception(sprintf('System can not create date from value: %s', $row[$field]));
                }

                $row[$field] = $date->format($this->outputFormat);
            }

            $newRows[] = $row;
        }

        $collection->setRows($newRows);
    }
}