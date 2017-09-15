<?php

namespace UR\Domain\DTO\Report\Transforms;

use UR\Service\DTO\Collection;

class PostAggregationTransform extends AbstractTransform implements TransformInterface
{
	const TRANSFORMS_TYPE = 'postAggregation';
	const FIELDS_KEY = 'fields';
	const FIELD_NAME_KEY = 'fieldName';
	const EXPRESSION_KEY = 'expression';

	/**
	 * @var string
	 */
	protected $fieldName;

	/**
	 * @var string
	 */
	protected $expression;

	function __construct(array $data)
	{
		parent::__construct();

		if (!array_key_exists(self::FIELD_NAME_KEY, $data)
			|| !array_key_exists(self::EXPRESSION_KEY, $data)
		) {
			throw new \Exception(sprintf('either "fieldName" or "expression" is missing'));
		}

		$this->fieldName = $data[self::FIELD_NAME_KEY];
		$this->expression = $data[self::EXPRESSION_KEY];
	}

	/**
	 * @param Collection $collection
	 * @param array $metrics
	 * @param array $dimensions
	 * @param $outputJoinField
	 * @return Collection
	 */
	public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
	{
		return $collection;
	}

	public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
	{

	}

	/**
	 * @return string
	 */
	public function getFieldName()
	{
		return $this->fieldName;
	}

	/**
	 * @return string
	 */
	public function getExpression()
	{
		return $this->expression;
	}
}