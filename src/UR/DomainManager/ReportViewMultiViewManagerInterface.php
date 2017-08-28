<?php

namespace UR\DomainManager;

use UR\Model\Core\ReportViewInterface;

interface ReportViewMultiViewManagerInterface extends ManagerInterface
{
    /**
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function getBySubView(ReportViewInterface $reportView);
}