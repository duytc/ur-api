<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use UR\Domain\DTO\Report\ParamsInterface;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportController extends FOSRestController
{
    /**
     * @Rest\Get("/platform")
     *
     * @Rest\QueryParam(name="reportViewDataSets", nullable=true)
     * @Rest\QueryParam(name="fieldTypes", nullable=true)
     * @Rest\QueryParam(name="joinBy", nullable=true)
     * @Rest\QueryParam(name="transforms", nullable=true)
     * @Rest\QueryParam(name="weightedCalculations", nullable=true)
     * @Rest\QueryParam(name="multiView", nullable=true)
     * @Rest\QueryParam(name="reportViewMultiViews", nullable=true)
     * @Rest\QueryParam(name="showInTotal", nullable=true)
     * @Rest\QueryParam(name="formats", nullable=true)
     * @Rest\QueryParam(name="subReportsIncluded", nullable=true)
     * @Rest\QueryParam(name="userDefineDateRange", nullable=true)
     * @Rest\QueryParam(name="startDate", nullable=true)
     * @Rest\QueryParam(name="endDate", nullable=true)
     * @Rest\QueryParam(name="isShowDataSetName", nullable=true)
     * @Rest\QueryParam(name="id", nullable=true)
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searches", nullable=true)
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="reportViewDataSets", "dataType"="array", "required"=false, "description"="list of data set id to build report"},
     *      {"name"="fieldTypes", "dataType"="array", "required"=false, "description"="list of fields accompanied with their corresponding type"},
     *      {"name"="searches", "dataType"="array", "required"=false, "description"="list of fields with their filter value"},
     *      {"name"="joinBy", "dataType"="array", "required"=false, "description"="filter descriptor"},
     *      {"name"="transforms", "dataType"="string", "required"=false, "description"="transform descriptor"},
     *      {"name"="weightedCalculations", "dataType"="string", "required"=false, "description"="weighted value calculations descriptor"},
     *      {"name"="multiView", "dataType"="string", "required"=false, "description"="specify the current report is a multi view report"},
     *      {"name"="reportViewMultiViews", "dataType"="string", "required"=false, "description"="report views descriptor"},
     *      {"name"="showInTotal", "dataType"="string", "required"=false, "description"="those fields that are allowed to be shown in Total area"},
     *      {"name"="formats", "dataType"="string", "required"=false, "description"="format descriptor"},
     *      {"name"="subReportsIncluded", "dataType"="bool", "required"=false, "description"="include sub reports in multi view report"},
     *      {"name"="userDefineDateRange", "dataType"="bool", "required"=false, "description"="user define data range in multi view report"},
     *      {"name"="startDate", "dataType"="string", "required"=false, "description"="start date in multi view report"},
     *      {"name"="endDate", "dataType"="string", "required"=false, "description"="end date in multi view report"},
     *      {"name"="isShowDataSetName", "dataType"="bool", "required"=false, "description"="show data set name or not"},
     *      {"name"="id", "dataType"="bool", "required"=false, "description"="show data set name or not"}
     *  }
     * )
     *
     * @return array
     */
    public function indexAction()
    {
        $params = $this->getParams();
        $reportViewRepository = $this->get('ur.repository.report_view');

        if ($params->getReportViewId() !== null) {
            $reportViewRepository->updateLastRun($params->getReportViewId());
        }

        return $this->getReportBuilder()->getReport($params);
    }

    /**
     * @return ParamsInterface
     */
    protected function getParams()
    {
        $params = $this->get('fos_rest.request.param_fetcher')->all($strict = true);
        return $this->get('ur.services.report.params_builder')->buildFromArray($params);
    }

    protected function getReportBuilder()
    {
        return $this->get('ur.services.report.report_builder');
    }
}
