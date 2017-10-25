<?php
namespace UR\Domain\DTO\Report\Transforms;

use UR\Service\DTO\Collection;

interface TransformInterface
{
	const TRANSFORM_TYPE_KEY = 'type';
	const TRANSFORM_IS_POST_KEY = 'isPostGroup';

	const ADD_FIELD_TRANSFORM = 'addField';
	const ADD_CALCULATED_FIELD_TRANSFORM = 'addCalculatedField';
	const ADD_CONDITION_VALUE_TRANSFORM = 'addConditionValue';
	const COMPARISON_PERCENT_TRANSFORM = 'comparisonPercent';
	const GROUP_TRANSFORM = 'groupBy';
	const AGGREGATION_TRANSFORM = 'aggregation';
	const POST_AGGREGATION_TRANSFORM = 'postAggregation';
	const SORT_TRANSFORM = 'sortBy';
    const REPLACE_TEXT_TRANSFORM = 'replaceText';
	const FORMAT_NUMBER_TRANSFORM = 'number';
	const FORMAT_DATE_TRANSFORM = 'date';

	const FIELDS_TRANSFORM = 'fields';

	/**
	 * @param Collection $collection
	 * @param array $metrics
	 * @param array $dimensions
	 * @param $outputJoinField
	 * @return mixed
	 */
	public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField);

	/**
	 * @param array $metrics
	 * @param array $dimensions
	 * @return mixed
	 */
	public function getMetricsAndDimensions(array &$metrics, array &$dimensions);

	/**
	 * @return mixed
	 */
	public function getTransformsType();

	/**
	 * @return mixed
	 */
	public function getIsPostGroup();
}