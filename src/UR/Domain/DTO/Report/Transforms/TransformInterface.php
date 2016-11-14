<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

interface TransformInterface
{
    const TRANSFORM_TYPE_KEY = 'transformType';

    const ADD_FIELD_TRANSFORM = 'addField';
    const ADD_CALCULATED_FIELD_TRANSFORM = 'addCalculatedField';
    const GROUP_TRANSFORM = 'group';
    const SORT_TRANSFORM = 'sort';
    const FORMAT_NUMBER_TRANSFORM = 'formatNumber';
    const FORMAT_DATE_TRANSFORM = 'formatDate';

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function transform(Collection $collection);
}