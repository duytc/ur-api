<?php


namespace UR\Domain\DTO\Report\Formats;


use UR\Exception\InvalidArgumentException;

abstract class AbstractFormat implements FormatInterface
{
    const FIELDS_NAME_KEY = 'fields';

    /** @var array */
    protected $fields;

    public function __construct(array $data)
    {
        if (!array_key_exists(self::FIELDS_NAME_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        if (!is_array($data[self::FIELDS_NAME_KEY])) {
            throw new InvalidArgumentException('"fields" must be array');
        }

        $this->fields = $data[self::FIELDS_NAME_KEY];
    }

    /**
     * @inheritdoc
     */
    public function getFields()
    {
        return $this->fields;
    }
}