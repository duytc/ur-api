<?php

namespace UR\Domain\DTO\Report\Filters;

use UR\Service\DTO\Report\ReportResult;

abstract class AbstractFilter
{
	const TYPE_DATE = 1;
	const TYPE_TEXT = 2;
	const TYPE_NUMBER = 3;

	/**
	 * @var string
	 */
	protected $fieldName;

	/**
	 * @var int
	 */
	protected $fieldType;

	/**
	 * @return string
	 */
	public function getFieldName()
	{
		return $this->fieldName;
	}

	/**
	 * @param $fieldName
	 * @return $this
	 */
	public function setFieldName($fieldName)
	{
		$this->fieldName = $fieldName;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getFieldType()
	{
		return $this->fieldType;
	}

	public function trimTrailingAlias($dataSetId)
	{
		$this->fieldName = str_replace(sprintf('_%d', $dataSetId), '', $this->fieldName);
		return $this;
	}

	protected function updateTotal(ReportResult $result)
	{
		$reports = $result->getReports();
		$totalValues = $result->getTotal();
		$keys = array_keys($totalValues);

		if (count($reports) == 0) {
			return $result;
		}

		foreach ($keys as $key) {
			$totalValues[$key] = 0;
			foreach ($reports as $report) {
				$totalValues[$key] += $report[$key];
			}
		}

		$result->setTotal($totalValues);

		return $result;
	}

	protected function updateAverages(ReportResult $result)
	{
		$reports = $result->getReports();
		$averagesValues = $result->getAverage();
		$keys = array_keys($averagesValues);

		if (count($reports) == 0) {
			return $result;
		}

		foreach ($keys as $key) {
			$total = 0;
			$averagesValues[$key] = 0;
			foreach ($reports as $report) {
				$total += $report[$key];
			}
			$averagesValues[$key] = $total / count($reports);
		}

		$result->setAverage($averagesValues);

		return $result;
	}

	abstract public function doFilter(ReportResult $reportsCollections);

	public function filter(ReportResult $reportsCollections)
	{
		$filterReports = $this->doFilter($reportsCollections);
		$updateTotalReport = $this->updateTotal($filterReports);

		return $this->updateAverages($updateTotalReport);
	}

}