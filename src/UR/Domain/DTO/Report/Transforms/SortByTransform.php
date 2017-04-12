<?php

namespace UR\Domain\DTO\Report\Transforms;

use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class SortByTransform extends AbstractTransform implements TransformInterface
{
	const TRANSFORMS_TYPE = 'sortBy';
	const SORT_DESC = 'desc';
	const SORT_ASC = 'asc';
	const FIELDS_KEY = 'names';
	const SORT_DIRECTION_KEY = 'direction';

	protected $sortObjects;

	function __construct(array $sortObjects)
	{
		parent::__construct();

		foreach ($sortObjects as $sortObject) {

			if (!array_key_exists(self::FIELDS_KEY, $sortObject) || !array_key_exists(self::SORT_DIRECTION_KEY, $sortObject)) {
				throw new InvalidArgumentException('either "fields" or "direction" is missing');

			}

			$this->sortObjects[] = $sortObject;
		}

		if (count($this->sortObjects) !== 2) {
			throw new InvalidArgumentException('only "asc" and "desc" sort is supported');
		}

		$intersect = array_intersect($this->sortObjects[0][self::FIELDS_KEY], $this->sortObjects[1][self::FIELDS_KEY]);
		if (count($intersect) > 0) {
			throw new InvalidArgumentException(sprintf('"%s" are present in both sort direction', implode(',', $intersect)));
		}
	}

	/**
	 * @param Collection $collection
	 * @param array $metrics
	 * @param array $dimensions
	 * @param $outputJoinField
	 * @return mixed
	 */
	public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
	{
		$excludeFields = [];
		$rows = $collection->getRows();
		$params = [];
		// collect column data
		foreach ($rows as $row) {
			foreach ($this->sortObjects as $sortObject) {
				foreach ($sortObject[self::FIELDS_KEY] as $field) {
					if (!array_key_exists($field, $row)) {
						$excludeFields[] = $field;
						break;
					}
					${$field . "values"}[] = $row[$field];
				}
			}
		}

		// build param
		foreach ($this->sortObjects as $sortObject) {
			foreach ($sortObject[self::FIELDS_KEY] as $field) {
				if (in_array($field, $excludeFields)) {
					break;
				}
				$params[] = ${$field . "values"};
				if ($sortObject[self::SORT_DIRECTION_KEY] === self::SORT_ASC) {
					$params[] = SORT_ASC;
				} else {
					$params[] = SORT_DESC;
				}
			}
		}

		$params[] = &$rows;

		call_user_func_array('array_multisort', $params);
		$collection->setRows($rows);
	}

	public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
	{
		// nothing changed in metrics and dimensions
	}
}