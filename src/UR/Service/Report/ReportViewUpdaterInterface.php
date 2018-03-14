<?php

namespace UR\Service\Report;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Worker\Job\Linear\AlterDataSetTableSubJob;

interface ReportViewUpdaterInterface
{
	const NEW_FIELDS = AlterDataSetTableSubJob::NEW_FIELDS;
	const UPDATE_FIELDS = AlterDataSetTableSubJob::UPDATE_FIELDS;
	const DELETED_FIELDS = AlterDataSetTableSubJob::DELETED_FIELDS;

	/**
	 * @param ReportViewInterface $reportView
	 * @param DataSetInterface $changedDataSet
	 * @param array $newFields
	 * @param array $updatedFields
	 * @param array $deletedFields
	 * @return ReportViewInterface
	 */
	public function refreshSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet, array $newFields = [], array $updatedFields = [], array $deletedFields = []);
}