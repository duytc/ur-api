<?php

namespace UR\Bundle\PublicBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\ReportViewInterface;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportViewController extends FOSRestController
{
    /**
     * Get shared report
     *
     * @Rest\Get("/reportviews/{id}/sharedReports")
     *
     * @Rest\QueryParam(name="token", nullable=false)
     *
     * @ApiDoc(
     *    section = "public",
     *    resource = true,
     *    statusCodes = {
     *       200 = "Returned when successful"
     *    },
     *    parameters={
     *       {"name"="sharedKey", "dataType"="string", "required"=true, "description"="the shared key to view report"}
     *    }
     * )
     *
     * @param Request $request
     * @param $id
     * @return array
     */
    public function getSharedReportsAction(Request $request, $id)
    {
        $reportView = $this->get('ur.domain_manager.report_view')->find($id);
        if (!($reportView instanceof ReportViewInterface)) {
            throw new BadRequestHttpException('Invalid ReportView');
        }

        $sharedKey = $request->query->get('token', null);
        if (null == $sharedKey || $sharedKey != $reportView->getSharedKey()) {
            throw new BadRequestHttpException('Invalid token');
        }

        $params = $this->getParams($reportView);
        $reportResult = $this->getReportBuilder()->getReport($params);
        $report = $reportResult->toArray();

        // important: append element "reportView" to reportResult, only for sharedReport
        // because sharedReport can not communicate to api to get reportView
        $report['reportView'] = $reportView;

        return $report;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return ParamsInterface formatted as:
     * {
     *      {"name"="dataSets", "dataType"="array", "required"=false, "description"="list of data set id to build report"},
     *      {"name"="fieldTypes", "dataType"="array", "required"=false, "description"="list of fields accompanied with their corresponding type"},
     *      {"name"="joinBy", "dataType"="string", "required"=false, "description"="filter descriptor"},
     *      {"name"="transforms", "dataType"="string", "required"=false, "description"="transform descriptor"},
     *      {"name"="weightedCalculations", "dataType"="string", "required"=false, "description"="weighted value calculations descriptor"},
     *      {"name"="filters", "dataType"="string", "required"=false, "description"="filters descriptor for multi view report"},
     *      {"name"="multiView", "dataType"="string", "required"=false, "description"="specify the current report is a multi view report"},
     *      {"name"="reportViews", "dataType"="string", "required"=false, "description"="report views descriptor"},
     *      {"name"="showInTotal", "dataType"="string", "required"=false, "description"="those fields that are allowed to be shown in Total area"},
     *      {"name"="formats", "dataType"="string", "required"=false, "description"="format descriptor"},
     *      {"name"="subReportsIncluded", "dataType"="bool", "required"=false, "description"="include sub reports in multi view report"}
     * }
     * @see UR\Bundle\ReportApiBundle\Controller\ReportController
     */
    protected function getParams($reportView)
    {
        return $this->get('ur.services.report.params_builder')->buildFromReportViewForSharedReport($reportView);
    }

    protected function getReportBuilder()
    {
        return $this->get('ur.services.report.report_builder');
    }
}