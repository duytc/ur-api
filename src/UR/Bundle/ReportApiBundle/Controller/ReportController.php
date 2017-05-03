<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportController extends FOSRestController
{
    /**
     * @Rest\Post("/reportview/data")
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return array
     */
    public function reportAction(Request $request)
    {
        $params = $this->get('ur.services.report.params_builder')->buildFromArray($request->request->all());
        $reportViewRepository = $this->get('ur.repository.report_view');

        if ($params->getReportViewId() !== null) {
            $reportViewRepository->updateLastRun($params->getReportViewId());
        }

        return $this->get('ur.services.report.report_builder')->getReport($params);
    }
}
