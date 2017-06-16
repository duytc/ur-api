<?php

namespace UR\Domain\DTO\Report\Transforms;

use UR\Exception\RuntimeException;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\StringUtilTrait;

class GroupByTransform extends AbstractTransform implements TransformInterface
{
	use StringUtilTrait;
	const TRANSFORMS_TYPE = 'groupBy';
	const TIMEZONE_KEY = 'timezone';
	const FIELDS_KEY = 'fields';
	const DEFAULT_TIMEZONE = 'UTC';

	/**
	 * @var array
	 */
	protected $fields;

	protected $timezone;

	function __construct(array $data, $timezone = self::DEFAULT_TIMEZONE)
	{
		parent::__construct();

		$this->fields = $data;
		$this->timezone = $timezone;
	}

	public function addField($field)
	{
		$this->fields[] = $field;
		return $this;
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
		$results = $this->getGroupedReport($this->getFields(), $collection, $metrics, $dimensions, $outputJoinField);
		$collection->setRows($results);
		$collection->setColumns(array_merge($metrics, $dimensions));

		return $collection;
	}

	public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
	{

	}

	/**
	 * @param $groupingFields
	 * @param Collection $collection
	 * @param array $metrics
	 * @param array $dimensions
	 * @param $outputJoinField
	 * @return array
	 */
	protected function getGroupedReport($groupingFields, Collection $collection, array $metrics, array &$dimensions, array $outputJoinField)
	{
		$groupedReports = $this->generateGroupedArray($groupingFields, $collection, $dimensions, $outputJoinField);
		foreach ($groupingFields as $index => $groupingField) {
			$groupingFields[$index] = $this->removeIdSuffix($groupingField);
		}

		$results = [];
		foreach ($groupedReports as $groupedReport) {
			$result = current($groupedReport);

			// clear all metrics
			foreach ($result as $key => $value) {
				if (in_array($key, $this->fields)) {
					continue;
				}

				if ($value && (in_array($key, $metrics) || in_array($key, $dimensions)) && in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL])) {
					$result[$key] = 0;
				}
			}

			foreach ($groupedReport as $report) {
				foreach ($report as $key => $value) {
					if ($value && in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL]) && !in_array($key, $this->fields)) {
						$result[$key] += $value;
					}
				}
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * @param $groupingFields
	 * @param Collection $collection
	 * @param $dimensions
	 * @param $outputJoinField
	 * @return array
	 * @throws \Exception
	 */
	protected function generateGroupedArray($groupingFields, Collection $collection, &$dimensions, array $outputJoinField)
	{
		$groupedArray = [];
		$rows = $collection->getRows();

		foreach ($groupingFields as $index => $groupingField) {
			$fieldWithoutSuffix = $this->removeIdSuffix($groupingField);
			if (in_array($fieldWithoutSuffix, $outputJoinField)) {
				$groupingFields[$index] = $fieldWithoutSuffix;
			}
		}

		foreach ($rows as &$report) {
			$key = '';
			foreach ($groupingFields as $groupField) {
				if (!array_key_exists($groupField, $report)) {
					continue;
				}

				if (empty($report[$groupField])) {
					continue;
				}

				if ($collection->getTypeOf($groupField) == FieldType::DATETIME) {
					$normalizedDate = $this->normalizeTimezone($report[$groupField]);
					$report[$groupField] = $normalizedDate->format('Y-m-d H:i:s');
					$key .= $normalizedDate->format('Y-m-d');
					continue;
				}

				$key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
			}

			$key = md5($key);
			$groupedArray[$key][] = $report;
		}

		return $groupedArray;
	}

	/**
	 * @param $value
	 * @return \DateTime
     */
	private function normalizeTimezone($value)
	{
		$date = \DateTime::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone(self::DEFAULT_TIMEZONE));

		if ($date instanceof \DateTime) {
			$date->setTimezone(new \DateTimeZone($this->timezone));
			return $date->setTime(0,0);
		}

		$date = new \DateTime($value, new \DateTimeZone($this->timezone));

		if (!$date instanceof \DateTime) {
			return $date->setTime(0, 0);
		}

		throw new RuntimeException('not found any invalid date format');
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