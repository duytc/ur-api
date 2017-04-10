<?php

namespace UR\Service\Report;


use UR\Model\Core\ReportViewInterface;

interface CloneReportViewInterface
{
    const CLONE_REPORT_VIEW_NAME = 'name';
    const CLONE_REPORT_VIEW_ALIAS = 'alias';
    const CLONE_REPORT_VIEW_TRANSFORM = 'transforms';
    const CLONE_REPORT_VIEW_FORMAT = 'formats';
    const CLONE_REPORT_VIEW_FILTER = 'filters';
    const CLONE_REPORT_VIEW_DATA_SET = 'dataSet';
    const CLONE_REPORT_VIEW_SUB_VIEW = 'subView';

    /**
     * clone report view base on clone settings
     *
     * @param ReportViewInterface $reportView
     * @param array $cloneSettings
     * @return mixed
     */
    public function cloneReportView(ReportViewInterface $reportView, array $cloneSettings);
}