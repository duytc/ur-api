<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\Report\ParamsBuilder;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;

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

        $result = $this->get('ur.services.report.report_builder')->getReport($params);
        $result->generateReports();
        return $result;
    }

    /**
     * @Rest\Post("/reportview/{id}/reportResult")
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
     * @param $id
     * @return array
     */
    public function reportResultAction(Request $request, $id)
    {
        /*
        * prototype report params:

        {
            "startDate":"2016-12-01",
            "endDate":"2017-01-29",
            "searches":{
            },
            "limit":10,
            "page":1,
            "userDefineDimensions":[
                "report_view_alias"
            ],
            "userDefineMetrics":[
                "ad_impression_1",
                "revenue_1"
            ]
        }
        */
        $data = $request->request->all();

        $reportViewRepository = $this->get('ur.repository.report_view');
        $reportViewData = $reportViewRepository->find($id);

        if (!$reportViewData instanceof ReportViewInterface) {
            throw new InvalidArgumentException('Report view id is missing or not existing');
        }

        $params = $this->get('ur.services.report.params_builder')->buildFromReportViewAndParams($reportViewData, $data);

        if ($params->getReportViewId() !== null) {
            $reportViewRepository->updateLastRun($params->getReportViewId());
        }

        $result = $this->get('ur.services.report.report_builder')->getReport($params);
        $result->generateReports();

        return $result;
    }

    /**
     * @Rest\Post("/reportview/download")
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
    public function downloadAction(Request $request)
    {
        $request->request->remove('page');
        $request->request->remove('limit');

        $params = $this->get('ur.services.report.params_builder')->buildFromArray($request->request->all());
        $reportViewRepository = $this->get('ur.repository.report_view');

        if ($params->getReportViewId() !== null) {
            $reportViewRepository->updateLastRun($params->getReportViewId());
        }

        /** @var ReportResult $reportResult */
        $reportResult = $this->get('ur.services.report.report_builder')->getReport($params);
        // Set the filename of the download
        $filename = 'MyReport_Tagcade_' . date('Ymd') . '-' . date('His');

        // Output CSV-specific headers
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv";');
        header('Content-Transfer-Encoding: binary');

        // Open the output stream
        $fh = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();

        // CSV Header
        $header = array_values($reportResult->getColumns());
        fputcsv($fh, $header);

        // CSV Data
        foreach ($reportResult->getRows() as $report) {
            $line = [];
            foreach ($reportResult->getColumns() as $key => $value) {
                if (array_key_exists($key, $report)) {
                    $line[] = $report[$key];
                } else {
                    $line[] = null;
                }
            }
            fputcsv($fh, $line);
        }

        // Get the contents of the output buffer
        $string = ob_get_clean();

        // Stream the CSV data
        exit($string);
    }
}
