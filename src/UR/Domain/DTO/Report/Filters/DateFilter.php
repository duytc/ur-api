<?php

namespace UR\Domain\DTO\Report\Filters;

use DateTime;
use UR\Service\DTO\Report\ReportResult;

class DateFilter extends AbstractFilter implements DateFilterInterface
{
	const FIELD_TYPE_FILTER_KEY = 'type';
	const FILED_NAME_FILTER_KEY = 'field';
	const DATE_FORMAT_FILTER_KEY = 'format';
	const DATE_TYPE_FILTER_KEY = 'dateType';
	const DATE_VALUE_FILTER_KEY = 'dateValue';

	/* for date type */
	const DATE_TYPE_CUSTOM_RANGE = 'customRange';
	const DATE_TYPE_DYNAMIC = 'dynamic';
    const DATE_TYPE_USER_PROVIDED= 'userProvided';

	/* for date value in case "dateType" is customRange value */
	const DATE_VALUE_FILTER_START_DATE_KEY = 'startDate';
	const DATE_VALUE_FILTER_END_DATE_KEY = 'endDate';

	/* pre-defined date value in case "dateType" is customRange value */
	const DATE_DYNAMIC_VALUE_TODAY = 'today';
	const DATE_DYNAMIC_VALUE_YESTERDAY = 'yesterday';
	const DATE_DYNAMIC_VALUE_LAST_7_DAYS = 'last 7 days';
	const DATE_DYNAMIC_VALUE_LAST_30_DAYS = 'last 30 days';
	const DATE_DYNAMIC_VALUE_THIS_MONTH = 'this month';
	const DATE_DYNAMIC_VALUE_LAST_MONTH = 'last month';

	/* supported date types */
	public static $SUPPORTED_DATE_TYPES = [
		self::DATE_TYPE_CUSTOM_RANGE,
		self::DATE_TYPE_DYNAMIC,
        self::DATE_TYPE_USER_PROVIDED
	];

	/* supported date values in case "dateType" is customRange value */
	public static $SUPPORTED_DATE_DYNAMIC_VALUES = [
		self::DATE_DYNAMIC_VALUE_TODAY,
		self::DATE_DYNAMIC_VALUE_YESTERDAY,
		self::DATE_DYNAMIC_VALUE_LAST_7_DAYS,
		self::DATE_DYNAMIC_VALUE_LAST_30_DAYS,
		self::DATE_DYNAMIC_VALUE_THIS_MONTH,
		self::DATE_DYNAMIC_VALUE_LAST_MONTH
	];

	/** @var string */
	protected $dateType;

	/** @var string|array */
	protected $dateValue;
	/**
	 * @var string
	 */
	protected $dateFormat;

	/**
	 * @param array $dateFilter
	 * @throws \Exception
	 */
	public function __construct(array $dateFilter = null)
	{
		if (empty($dateFilter)) {
			return;
		}

		if (!array_key_exists(self::FIELD_TYPE_FILTER_KEY, $dateFilter)
			|| !array_key_exists(self::FILED_NAME_FILTER_KEY, $dateFilter)
			// || !array_key_exists(self::DATE_FORMAT_FILTER_KEY, $dateFilter)
			|| !array_key_exists(self::DATE_TYPE_FILTER_KEY, $dateFilter)
			|| !array_key_exists(self::DATE_VALUE_FILTER_KEY, $dateFilter)
		) {
			throw new \Exception (sprintf('Either parameters: %s, %s, %s, %s or %s not exits in date filter',
				self::FIELD_TYPE_FILTER_KEY,
				self::FILED_NAME_FILTER_KEY,
				self::DATE_FORMAT_FILTER_KEY,
				self::DATE_TYPE_FILTER_KEY,
				self::DATE_VALUE_FILTER_KEY));
		}

		$this->fieldName = $dateFilter[self::FILED_NAME_FILTER_KEY];
		$this->fieldType = $dateFilter[self::FIELD_TYPE_FILTER_KEY];
		// $this->dateFormat = $dateFilter[self::DATE_FORMAT_FILTER_KEY];
		$this->dateType = $dateFilter[self::DATE_TYPE_FILTER_KEY];
		$this->dateValue = $dateFilter[self::DATE_VALUE_FILTER_KEY];

		// validate dateValue
		$this->validateDateValue();
	}

	/**
	 * @return string
	 */
	public function getDateFormat()
	{
		return $this->dateFormat;
	}

	/**
	 * @return string
	 */
	public function getDateType()
	{
		return $this->dateType;
	}

	/**
	 * @return array|string
	 */
	public function getDateValue()
	{
		return $this->dateValue;
	}

	/**
	 * @return array
	 */
	public function getStartDate()
	{
		if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType || self::DATE_TYPE_USER_PROVIDED == $this->dateType) {
			return $this->dateValue[self::DATE_VALUE_FILTER_START_DATE_KEY];
		}

		// if (self::DATE_TYPE_DYNAMIC == $this->dateType)
		return $this->getDynamicDate()[0];
	}

	/**
	 * @return mixed
	 */
	public function getEndDate()
	{
		if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType || self::DATE_TYPE_USER_PROVIDED == $this->dateType) {
			return $this->dateValue[self::DATE_VALUE_FILTER_END_DATE_KEY];
		}

		// if (self::DATE_TYPE_DYNAMIC == $this->dateType)
		return $this->getDynamicDate()[1];
	}

	/**
	 * validate date value due to date type
	 * @throws \Exception
	 */
	private function validateDateValue()
	{
		if (!in_array($this->dateType, self::$SUPPORTED_DATE_TYPES)) {
			throw new \Exception (sprintf('not supported date type %s', $this->dateType));
		}

		if (self::DATE_TYPE_DYNAMIC == $this->dateType) {
			if (!in_array($this->dateValue, self::$SUPPORTED_DATE_DYNAMIC_VALUES)) {
				throw new \Exception (sprintf('not supported date value %s', $this->dateValue));
			}
		}

		if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType || self::DATE_TYPE_USER_PROVIDED == $this->dateType) {
			if (!is_array($this->dateValue)
				|| !array_key_exists(self::DATE_VALUE_FILTER_START_DATE_KEY, $this->dateValue)
				|| !array_key_exists(self::DATE_VALUE_FILTER_END_DATE_KEY, $this->dateValue)
			) {
				throw new \Exception (sprintf('missing either %s or %s in date value %s',
					self::DATE_VALUE_FILTER_START_DATE_KEY,
					self::DATE_VALUE_FILTER_END_DATE_KEY,
					$this->dateValue));
			}
		}
	}

	/**
	 * get dynamic date from date value
	 */
	private function getDynamicDate()
	{
		if (self::DATE_TYPE_DYNAMIC != $this->dateType) {
			return ['', ''];
		}

		$startDate = '';
		$endDate = '';

		if (self::DATE_DYNAMIC_VALUE_TODAY == $this->dateValue) {
			$startDate = $endDate = date('Y-m-d', strtotime('now'));
		}

		if (self::DATE_DYNAMIC_VALUE_YESTERDAY == $this->dateValue) {
			$startDate = $endDate = date('Y-m-d', strtotime('-1 day'));
		}

		if (self::DATE_DYNAMIC_VALUE_LAST_7_DAYS == $this->dateValue) {
			$startDate = date('Y-m-d', strtotime('-7 day'));
			$endDate = date('Y-m-d', strtotime('-1 day'));
		}

		if (self::DATE_DYNAMIC_VALUE_LAST_30_DAYS == $this->dateValue) {
			$startDate = date('Y-m-d', strtotime('-30 day'));
			$endDate = date('Y-m-d', strtotime('-1 day'));
		}

		if (self::DATE_DYNAMIC_VALUE_THIS_MONTH == $this->dateValue) {
			$startDate = date('Y-m-01', strtotime('this month'));
			$endDate = date('Y-m-d', strtotime('now'));
		}

		if (self::DATE_DYNAMIC_VALUE_LAST_MONTH == $this->dateValue) {
			$startDate = date('Y-m-01', strtotime('last month'));
			$endDate = date('Y-m-t', strtotime('last month'));
		}

		return [$startDate, $endDate];
	}

	public function doFilter(ReportResult $reportsCollections)
	{
		$startDate = $this->getStartDate();
		$endDate = $this->getEndDate();

		if (!$startDate instanceof DateTime) {
			$startDate = new DateTime($startDate);
		}

		if (!$endDate instanceof DateTime) {
			$endDate = new DateTime($endDate);
		}

		$endDate->format('Y-m-d');
		$startDate->format('Y-m-d');

		$reports = $reportsCollections->getReports();
		$filterReports = array_filter($reports, function ($report) use ($startDate, $endDate) {
			if (!array_key_exists($this->getFieldName(), $report)) {
				return false;
			}

			$stringDate = $report[$this->getFieldName()];
			$dateValue = new DateTime($stringDate);
			$dateValue->format('Y-m-d');

			if ($dateValue >= $startDate && $dateValue <= $endDate) {
				return true;
			}
			return false;

		}, ARRAY_FILTER_USE_BOTH);

		$reportsCollections->setReports($filterReports);

		return $reportsCollections;
	}

	/**
	 * @param string $dateFormat
	 * @return $this
	 */
	public function setDateFormat(string $dateFormat)
	{
		$this->dateFormat = $dateFormat;
		return $this;
	}

	/**
	 * @param string $dateType
	 * @return $this
	 */
	public function setDateType(string $dateType)
	{
		$this->dateType = $dateType;
		return $this;
	}

	/**
	 * @param array|string $dateValue
	 * @return $this
	 */
	public function setDateValue($dateValue)
	{
		$this->dateValue = $dateValue;
		return $this;
	}
}