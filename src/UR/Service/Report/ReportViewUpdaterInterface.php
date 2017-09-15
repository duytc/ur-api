<?php

namespace UR\Service\Report;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;

interface ReportViewUpdaterInterface
{
	const NEW_FIELDS = 'new_fields';
	const UPDATE_FIELDS = 'update_fields';
	const DELETED_FIELDS = 'deleted_fields';

	/**
	 * @param ReportViewInterface $reportView
	 * @param DataSetInterface $changedDataSet
	 * @return ReportViewInterface
	 */
	public function refreshSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet);
}