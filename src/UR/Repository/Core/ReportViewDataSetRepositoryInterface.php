<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ReportViewInterface;

interface ReportViewDataSetRepositoryInterface extends ObjectRepository
{
    /**
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function getByReportView(ReportViewInterface $reportView);
}