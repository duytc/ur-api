<?php

namespace UR\Domain\DTO\Report\Transforms;

use UR\Service\DTO\Collection;
use UR\Service\StringUtilTrait;

class AggregationTransform extends AbstractTransform implements TransformInterface
{
	use StringUtilTrait;
	const TRANSFORMS_TYPE = 'aggregate';
	const FIELDS_KEY = 'fields';

	/**
	 * @var array
	 */
	protected $fields;

	function __construct(array $data)
	{
		parent::__construct();

		$this->fields = $data;
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
	 * @return mixed
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * @param array $fields
	 * @return self
	 */
	public function setFields($fields)
	{
		$this->fields = $fields;
		return $this;
	}
}