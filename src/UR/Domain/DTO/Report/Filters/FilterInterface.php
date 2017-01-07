<?php

namespace UR\Domain\DTO\Report\Filters;

use UR\Service\DTO\Report\ReportResult;

interface FilterInterface
{
	/**
	 * @return string
	 */
	public function getFieldName();

	/**
	 * @param $fieldName
	 * @return mixed
	 */
	public function setFieldName($fieldName);

	/**
	 * @return int
	 */
	public function getFieldType();

	/**
	 * @param $dataSetId
	 * @return mixed
	 */
	public function trimTrailingAlias($dataSetId);

	/**
	 * @param ReportResult $reportsCollections
	 * @return mixed
	 */

	public function filter(ReportResult $reportsCollections);
}