<?php

namespace UR\Domain\DTO\Report\Filters;

use UR\Service\DTO\Report\ReportResult;

class TextFilter extends AbstractFilter implements TextFilterInterface
{
	const COMPARISON_TYPE_EQUAL = 'equal';
	const COMPARISON_TYPE_NOT_EQUAL = 'not equal';
	const COMPARISON_TYPE_CONTAINS = 'contains';
	const COMPARISON_TYPE_NOT_CONTAINS = 'not contains';
	const COMPARISON_TYPE_START_WITH = 'start with';
	const COMPARISON_TYPE_END_WITH = 'end with';
	const COMPARISON_TYPE_IN = 'in';
	const COMPARISON_TYPE_NOT_IN = 'not in';
	const COMPARISON_TYPE_NULL = 'isEmpty';
	const COMPARISON_TYPE_NOT_NULL = 'isNotEmpty';

	const FIELD_TYPE_FILTER_KEY = 'type';
	const FILED_NAME_FILTER_KEY = 'field';
	const COMPARISON_TYPE_FILTER_KEY = 'comparison';
	const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

	public static $SUPPORTED_COMPARISON_TYPES = [
		self::COMPARISON_TYPE_EQUAL,
		self::COMPARISON_TYPE_NOT_EQUAL,
		self::COMPARISON_TYPE_CONTAINS,
		self::COMPARISON_TYPE_NOT_CONTAINS,
		self::COMPARISON_TYPE_START_WITH,
		self::COMPARISON_TYPE_END_WITH,
		self::COMPARISON_TYPE_IN,
		self::COMPARISON_TYPE_NOT_IN,
		self::COMPARISON_TYPE_NULL,
		self::COMPARISON_TYPE_NOT_NULL
	];

	/** @var string */
	protected $comparisonType;

	/** @var string|array due to comparisonType */
	protected $comparisonValue;

	/**
	 * @param array $textFilter
	 * @throws \Exception
	 */
	public function __construct(array $textFilter)
	{
		if (!array_key_exists(self::FILED_NAME_FILTER_KEY, $textFilter)
			|| !array_key_exists(self::FIELD_TYPE_FILTER_KEY, $textFilter)
			|| !array_key_exists(self::COMPARISON_TYPE_FILTER_KEY, $textFilter)
			|| !array_key_exists(self::COMPARISON_VALUE_FILTER_KEY, $textFilter)
		) {
			throw new \Exception(sprintf('Either parameters: %s, %s, %s or %s does not exist in text filter',
				self::FILED_NAME_FILTER_KEY, self::FIELD_TYPE_FILTER_KEY, self::COMPARISON_TYPE_FILTER_KEY, self::COMPARISON_VALUE_FILTER_KEY));
		}

		$this->fieldName = $textFilter[self::FILED_NAME_FILTER_KEY];
		$this->fieldType = $textFilter[self::FIELD_TYPE_FILTER_KEY];
		$this->comparisonType = $textFilter[self::COMPARISON_TYPE_FILTER_KEY];
		$this->comparisonValue = $textFilter[self::COMPARISON_VALUE_FILTER_KEY];

		// validate comparisonType
		$this->validateComparisonType();

		// validate comparisonValue
		$this->validateComparisonValue();
	}

	/**
	 * @inheritdoc
	 */
	public function getComparisonType()
	{
		return $this->comparisonType;
	}

	/**
	 * @inheritdoc
	 */
	public function getComparisonValue()
	{
		return $this->comparisonValue;
	}

	/**
	 * validate ComparisonType
	 *
	 * @throws \Exception
	 */
	private function validateComparisonType()
	{
		if (!in_array($this->comparisonType, self::$SUPPORTED_COMPARISON_TYPES)) {
			throw new \Exception(sprintf('Not supported comparisonType %s', $this->comparisonType));
		}
	}

	/**
	 * validate ComparisonValue
	 *
	 * @throws \Exception
	 */
	private function validateComparisonValue()
	{
		// expect array
		if ($this->comparisonType == self::COMPARISON_TYPE_CONTAINS
			|| $this->comparisonType == self::COMPARISON_TYPE_NOT_CONTAINS
			|| $this->comparisonType == self::COMPARISON_TYPE_START_WITH
			|| $this->comparisonType == self::COMPARISON_TYPE_END_WITH
			|| $this->comparisonType == self::COMPARISON_TYPE_IN
			|| $this->comparisonType == self::COMPARISON_TYPE_NOT_IN
		) {
			if (!is_array($this->comparisonValue)) {
				throw new \Exception(sprintf('Expect comparisonValue is array with comparisonType %s, got %s', $this->comparisonType, $this->comparisonValue));
			}
		}
	}

	public function doFilter(ReportResult $reportsCollections)
	{
		switch ($this->getComparisonType()) {
			case self::COMPARISON_TYPE_EQUAL:
				return $this->equalFilter($reportsCollections);
			case self::COMPARISON_TYPE_NOT_EQUAL:
				return $this->notEqualFilter($reportsCollections);
			case self::COMPARISON_TYPE_CONTAINS:
				return $this->containsFilter($reportsCollections);
			case self::COMPARISON_TYPE_NOT_CONTAINS:
				return $this->notContainsFilter($reportsCollections);
			case self::COMPARISON_TYPE_END_WITH:
				return $this->endWithFilter($reportsCollections);
			case self::COMPARISON_TYPE_START_WITH:
				return $this->startWithFilter($reportsCollections);
			case self::COMPARISON_TYPE_IN:
				return $this->inFilter($reportsCollections);
			case self::COMPARISON_TYPE_NOT_IN:
				return $this->notInFilter($reportsCollections);
			case self::COMPARISON_TYPE_NULL:
				return $this->nullFilter($reportsCollections);
			case self::COMPARISON_TYPE_NOT_NULL:
				return $this->notNullFilter($reportsCollections);
			default:
				return $reportsCollections;
		}
	}

	protected function equalFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			return (in_array($report[$this->getFieldName()], $this->getComparisonValue()));
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function notEqualFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			return (!in_array($report[$this->getFieldName()], $this->getComparisonValue()));
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function containsFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			$containsValues = $this->getComparisonValue();
			foreach ($containsValues as $containsValue) {
				if (false !== strpos($report[$this->getFieldName()], $containsValue)) {
					return true;
				}
			}
			return false;

		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function notContainsFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			$containsValues = $this->getComparisonValue();
			foreach ($containsValues as $containsValue) {
				if (false === strpos($report[$this->getFieldName()], $containsValue)) {
					return true;
				}
			}
			return false;

		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function startWithFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			$containsValues = $this->getComparisonValue();
			foreach ($containsValues as $containsValue) {
				if (substr($report[$this->getFieldName()], 0, strlen($containsValue)) === $containsValue) {
					return true;
				}
			}
			return false;

		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function endWithFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			$containsValues = $this->getComparisonValue();
			foreach ($containsValues as $containsValue) {
				$startPosition = strlen($report[$this->getFieldName()]) - strlen($containsValue);
				if (substr($report[$this->getFieldName()], $startPosition, strlen($containsValue)) === $containsValue) {
					return true;
				}
			}
			return false;

		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function inFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			return (in_array($report[$this->getFieldName()], $this->getComparisonValue()));
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function notInFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}

			return (!in_array($report[$this->getFieldName()], $this->getComparisonValue()));
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function nullFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			return null == $report[$this->getFieldName()];
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	protected function notNullFilter(ReportResult $reportsCollections)
	{
		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}
			return null != $report[$this->getFieldName()];
		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}
}