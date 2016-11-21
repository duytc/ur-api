<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class SortByTransform extends AbstractTransform implements SortByTransformInterface
{
    const PRIORITY = 4;
    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    const SORT_DIRECTION_ASC = SORT_ASC;
    const SORT_DIRECTION_DESC = SORT_DESC; //Importance: not change to use in array_multisort when sorting report

    const DEFAULT_SORT_DIRECTION = 'asc';
    const FIELDS_KEY = 'names';
    const SORT_DIRECTION_KEY = 'direction';

    /**
     * @var array
     */
    protected $fields;

    protected $direction;

    protected $sortDirection;

    function __construct(array $data)
    {
        parent::__construct();
        if (!array_key_exists(self::FIELDS_KEY, $data)) {
            throw new InvalidArgumentException('"fields" is missing');
        }

        $this->fields = $data[self::FIELDS_KEY];

        $this->direction = array_key_exists(self::SORT_DIRECTION_KEY, $data) ? $data[self::SORT_DIRECTION_KEY] : self::DEFAULT_SORT_DIRECTION;

        if ($this->direction === self::SORT_ASC) {
            $this->direction = self::SORT_DIRECTION_ASC;
        } else {
            $this->direction = self::SORT_DIRECTION_DESC;
        }
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return mixed
     */
    public function getDirection()
    {
        return $this->direction;
    }

    public function transform(Collection $collection)
    {

    }
}