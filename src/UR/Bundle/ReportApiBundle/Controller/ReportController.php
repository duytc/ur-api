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
 * @Security("has_role('ROLE_ADMIN') or (has_role('ROLE_PUBLISHER') and has_role('MODULE_UNIFIED_REPORT'))")
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportController extends FOSRestController
{
    /**
     * @Rest\Get("/platform")
     *
     * @Rest\QueryParam(name="dataSets", nullable=false)
     * @Rest\QueryParam(name="filters", nullable=true)
     * @Rest\QueryParam(name="transforms", nullable=true)*
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="dataSets", "dataType"="array", "required"=true, "description"="list of data set id to build report"},
     *      {"name"="filters", "dataType"="array", "required"=false, "description"="filter descriptor"},
     *      {"name"="transforms", "dataType"="array", "required"=false, "description"="transform descriptor"}
     *  }
     * )
     *
     * @return array
     */
    public function indexAction()
    {
        return $this->getReportBuilder()->getReport($this->getParams());
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
