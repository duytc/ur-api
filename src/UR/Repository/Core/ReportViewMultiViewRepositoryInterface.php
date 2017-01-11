<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ReportViewInterface;

interface ReportViewMultiViewRepositoryInterface extends ObjectRepository
{
    /**
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function getByReportView(ReportViewInterface $reportView);

    /**
     * @param ReportViewInterface $reportView
     * @return boolean
     */
    public function checkIfReportViewBelongsToMultiView(ReportViewInterface $reportView);
}