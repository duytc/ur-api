<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class GroupByTransform implements GroupByTransformInterface
{
    const FIELDS_KEY = 'fields';
    /**
     * @var array
     */
    protected $fields;

    function __construct(array $data)
    {
        if (!array_key_exists(self::FIELDS_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        if (!is_array($data[self::FIELDS_KEY])) {
            throw new InvalidArgumentException(' invalid "fields" is provided');
        }

        $this->fields = $data[self::FIELDS_KEY];
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function transform(Collection $collection)
    {
        // TODO: Implement transform() method.
    }
}