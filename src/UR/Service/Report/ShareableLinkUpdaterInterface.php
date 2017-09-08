<?php

namespace UR\Service\Report;

use UR\Model\Core\ReportViewInterface;

interface ShareableLinkUpdaterInterface
{
    /**
     * Goal: In shareable link, correct below fields:
     *    - Fields to share
     *    - Allow Dates Outside
     *    - Date Range (include start date and end date)
     * @param ReportViewInterface $reportView
     */
    public function updateShareableLinks(ReportViewInterface $reportView);
}