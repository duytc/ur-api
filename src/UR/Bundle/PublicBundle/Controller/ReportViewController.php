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

        return $this->getReportBuilder()->getReport($params);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return ParamsInterface formatted as:
     * {
     *    dataSets => [], // array, list of data set id to build report
     *    joinBy => "", // jsonString, filter descriptor
     *    transforms => "", // jsonString, transform descriptor
     *    calculations => "", // jsonString, weighted value calculations descriptor
     *    formats => "" // jsonString, format descriptor
     * }
     */
    protected function getParams($reportView)
    {
        $dataSets = is_array($reportView->getDataSets()) ? $reportView->getDataSets() : [];
        $joinBy = is_array($reportView->getJoinBy()) ? $reportView->getJoinBy() : [];
        $transforms = is_array($reportView->getTransforms()) ? $reportView->getTransforms() : [];
        $calculations = is_array($reportView->getWeightedCalculations()) ? $reportView->getWeightedCalculations() : [];
        $formats = is_array($reportView->getFormats()) ? $reportView->getFormats() : [];

        $params = [
            'dataSets' => json_encode($dataSets),
            'joinBy' => json_encode($joinBy),
            'transforms' => json_encode($transforms),
            'calculations' => json_encode($calculations),
            'formats' => json_encode($formats)
        ];

        return $this->get('ur.services.report.params_builder')->buildFromArray($params);
    }

    protected function getReportBuilder()
    {
        return $this->get('ur.services.report.report_builder');
    }
}