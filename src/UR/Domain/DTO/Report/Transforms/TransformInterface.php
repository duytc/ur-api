<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

interface TransformInterface
{
    const TRANSFORM_TYPE_KEY = 'type';

    const ADD_FIELD_TRANSFORM = 'addField';
    const ADD_CALCULATED_FIELD_TRANSFORM = 'addCalculatedField';
    const GROUP_TRANSFORM = 'groupBy';
    const SORT_TRANSFORM = 'sortBy';
    const FORMAT_NUMBER_TRANSFORM = 'number';
    const FORMAT_DATE_TRANSFORM = 'date';

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function transform(Collection $collection);
}