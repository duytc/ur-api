<?php
namespace UR\Domain\DTO\Report\Transforms;

use UR\Service\DTO\Collection;

interface TransformInterface
{
    const TRANSFORM_TYPE_KEY = 'type';

    const ADD_FIELD_TRANSFORM = 'addField';
    const ADD_CALCULATED_FIELD_TRANSFORM = 'addCalculatedField';
    const COMPARISON_PERCENT_TRANSFORM = 'comparisonPercent';
    const GROUP_TRANSFORM = 'groupBy';
    const SORT_TRANSFORM = 'sortBy';
    const FORMAT_NUMBER_TRANSFORM = 'number';
    const FORMAT_DATE_TRANSFORM = 'date';

    const FIELDS_TRANSFORM = 'fields';

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed
     */
    public function transform(Collection $collection,  array &$metrics, array &$dimensions, $joinBy = null);

    /**
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function getMetricsAndDimensions(array &$metrics, array &$dimensions);

    /**
     * @return int
     */
    public function getPriority();
}