<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use UR\Service\DTO\Report\ReportResult;

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

        /** @var ReportResult $reportResult */
        $reportResult = $this->reportAction($request);
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
        foreach ($reportResult->getReports() as $report) {
            $line = [];
            foreach ($reportResult->getColumns() as $key => $value){
                $line[] = $report[$key];
            }
            fputcsv($fh, $line);
        }

        // Get the contents of the output buffer
        $string = ob_get_clean();

        // Stream the CSV data
        exit($string);
    }
}
