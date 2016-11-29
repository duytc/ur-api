<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use UR\Domain\DTO\Report\Params;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\Report\ParamsBuilderInterface;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportController extends FOSRestController
{
    /**
     * @Rest\Get("/platform")
     *
     * @Rest\QueryParam(name="dataSets", nullable=true)
     * @Rest\QueryParam(name="joinBy", nullable=true)
     * @Rest\QueryParam(name="transforms", nullable=true)
     * @Rest\QueryParam(name="weightedCalculations", nullable=true)
     * @Rest\QueryParam(name="filters", nullable=true)
     * @Rest\QueryParam(name="multiView", nullable=true)
     * @Rest\QueryParam(name="reportViews", nullable=true)
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="dataSets", "dataType"="array", "required"=false, "description"="list of data set id to build report"},
     *      {"name"="joinBy", "dataType"="string", "required"=false, "description"="filter descriptor"},
     *      {"name"="transforms", "dataType"="string", "required"=false, "description"="transform descriptor"},
     *      {"name"="weightedCalculations", "dataType"="string", "required"=false, "description"="weighted value calculations descriptor"},
     *      {"name"="filters", "dataType"="string", "required"=false, "description"="filters descriptor for multi view report"},
     *      {"name"="multiView", "dataType"="string", "required"=false, "description"="specify the current report is a multi view report"},
     *      {"name"="reportViews", "dataType"="string", "required"=false, "description"="report views descriptor"}
     *  }
     * )
     *
     * @return array
     */
    public function indexAction()
    {
        $params = $this->getParams();
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
