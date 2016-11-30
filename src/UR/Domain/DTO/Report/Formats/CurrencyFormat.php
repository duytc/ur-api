<?php


namespace UR\Domain\DTO\Report\Formats;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class CurrencyFormat extends AbstractFormat implements CurrencyFormatInterface
{
    const CURRENCY_KEY = 'currency';

    const DEFAULT_CURRENCY = '$';

    /** @var int */
    protected $currency;

    function __construct(array $data)
    {
        parent::__construct($data);

        if (!array_key_exists(self::CURRENCY_KEY, $data)) {
            throw new InvalidArgumentException('"currency" is missing');
        }

        $this->currency = empty($data[self::CURRENCY_KEY]) ? self::DEFAULT_CURRENCY : $data[self::CURRENCY_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::FORMAT_PRIORITY_CURRENCY;
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

                $row[$field] = $this->getCurrency() . ' ' .  $row[$field];
            }

            $newRows[] = $row;
        }

        $collection->setRows($newRows);

        return $collection;
    }
}