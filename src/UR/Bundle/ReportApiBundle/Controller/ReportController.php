<?php

namespace UR\Bundle\ReportApiBundle\Controller;

use DateTime;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Bundle\ApiBundle\Controller\RestControllerAbstract;
use UR\Bundle\ReportApiBundle\Behaviors\DashBoardUtilTrait;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Handler\HandlerInterface;
use UR\Service\DTO\Report\ReportResult;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\Report\ParamsBuilder;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportController extends RestControllerAbstract implements ClassResourceInterface
{
    use DashBoardUtilTrait;

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

        if ($params->getReportViewId() !== null) {
            $this->one($params->getReportViewId());
            $reportViewRepository = $this->get('ur.repository.report_view');
            $reportViewRepository->updateLastRun($params->getReportViewId());
        }

        $result = $this->getReport($params);

        return $result;
    }

    /**
     * @Rest\Post("/reportview/comparison")
     *
     * @Rest\RequestParam(name="masterReport", nullable=false, description="the master report id")
     * @Rest\RequestParam(name="type", nullable=false, description="type of comparison, such as day-over-day, week-over-week, month-over-month or year-over-year")
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters = {
     *      {"name"="masterReport", "dataType"="number", "required"=true, "description"="the master report id"},
     *      {"name"="type", "dataType"="string", "required"=true, "description"="type of comparison, such as day-over-day, week-over-week, month-over-month, year-over-year"},
     *  }
     * )
     *
     * @param Request $request
     * @return array
     */
    public function getReportComparisonAction(Request $request)
    {
        /* get params from request */
        // get master report id
        $masterReportId = $request->request->get('masterReport');
        $masterReportId = filter_var($masterReportId, FILTER_VALIDATE_INT);
        if (false === $masterReportId || $masterReportId < 0) {
            throw new BadRequestHttpException('Expected masterReport is positive integer');
        }

        // get report view by master report id and also check permission
        $reportView = $this->one($masterReportId);
        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException(sprintf('Report view #%s does not existing', $masterReportId));
        }

        // validate ReportView To Get Comparison
        $this->validateReportViewToGetComparisonData($reportView);

        // get comparison type
        $comparisonType = $request->request->get('type');
        $SUPPORTED_COMPARISON_TYPES = [
            self::$COMPARISON_TYPE_DAY_OVER_DAY,
            self::$COMPARISON_TYPE_WEEK_OVER_WEEK,
            self::$COMPARISON_TYPE_MONTH_OVER_MONTH,
            self::$COMPARISON_TYPE_YEAR_OVER_YEAR
        ];

        if (empty($comparisonType) || !in_array($comparisonType, $SUPPORTED_COMPARISON_TYPES)) {
            throw new BadRequestHttpException(sprintf('Not support comparison type %s', $comparisonType));
        }

        /* get date field from report view. This is used for filter and draw chart in UI */
        $dateFieldAndFormat = $this->getDateFieldFromReportViewForDashboard($reportView);
        if (!$dateFieldAndFormat || !array_key_exists('field', $dateFieldAndFormat) || !array_key_exists('format', $dateFieldAndFormat)) {
            throw new BadRequestHttpException(sprintf('Could not get date field from master report #%d', $masterReportId));
        }

        $dateField = $dateFieldAndFormat['field'];

        /* build params to get report data for current and history */
        // build common params
        $requestParams = [
            ParamsBuilder::START_DATE => '', // override later
            ParamsBuilder::END_DATE => '', // override later
            ParamsBuilder::SORT_FIELD_KEY => sprintf('"%s"', $dateField),
            ParamsBuilder::ORDER_BY_KEY => 'asc'
        ];

        $params = $this->get('ur.services.report.params_builder')->buildFromReportViewAndParamsForDashboard($reportView, $requestParams);

        /* apply date range */
        // get startDate-endDate due to comparison type
        $startDateEndDate = $this->getStartDateEndDateDueToComparisonType($comparisonType);
        if (!is_array($startDateEndDate)) {
            throw new BadRequestHttpException(sprintf('Not support comparison type %s', $comparisonType));
        }

        // modify date range in params for current
        $paramsForToday = clone $params;
        $paramsForToday->setStartDate(new \DateTime($startDateEndDate['current']['startDate']));
        $paramsForToday->setEndDate(new \DateTime($startDateEndDate['current']['endDate']));

        // modify date range in params for history
        $paramsForYesterday = clone $params;
        $paramsForYesterday->setStartDate(new \DateTime($startDateEndDate['history']['startDate']));
        $paramsForYesterday->setEndDate(new \DateTime($startDateEndDate['history']['endDate']));

        /* get and return report */
        // get reports
        $currentReport = $this->getReport($paramsForToday);
        $historyReport = $this->getReport($paramsForYesterday);

        // unset not need fields from reports
        $currentReport = $this->getMinimizeReportForComparison($currentReport->toArray());
        $historyReport = $this->getMinimizeReportForComparison($historyReport->toArray());

        // do sum for same date in reports detail
        $reportViewFormatter = $this->get('ur.services.report.report_formatter');
        $currentReport = $this->doSumForSameDate($currentReport, $dateField, $params->getFormats(), $reportViewFormatter);
        $historyReport = $this->doSumForSameDate($historyReport, $dateField, $params->getFormats(), $reportViewFormatter);

        // add dateField
        $currentReport['dateField'] = $dateFieldAndFormat;
        $historyReport['dateField'] = $dateFieldAndFormat;

        $result = [
            'current' => $currentReport,
            'history' => $historyReport
        ];

        return $result;
    }

    /**
     * @Rest\Post("/reportview/overview")
     *
     * @Rest\RequestParam(name="masterReport", nullable=false, description="the master report id")
     * @Rest\RequestParam(name="startDate", nullable=false, description="start date of report, format Y-m-d")
     * @Rest\RequestParam(name="endDate", nullable=false, description="end date of report, format Y-m-d")
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters = {
     *      {"name"="masterReport", "dataType"="number", "required"=true, "description"="the master report id"},
     *      {"name"="startDate", "dataType"="string", "required"=true, "description"="start date of report, format Y-m-d"},
     *      {"name"="endDate", "dataType"="string", "required"=true, "description"="end date of report, format Y-m-d"},
     *  }
     * )
     *
     * @param Request $request
     * @return array
     */
    public function getReportOverviewAction(Request $request)
    {
        /* get params from request */
        // get master report id
        $masterReportId = $request->request->get('masterReport');
        $masterReportId = filter_var($masterReportId, FILTER_VALIDATE_INT);
        if (false === $masterReportId || $masterReportId < 0) {
            throw new BadRequestHttpException('Expected masterReport is positive integer');
        }

        // get report view by master report id and also check permission
        $reportView = $this->one($masterReportId);
        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException(sprintf('Report view #%s does not existing', $masterReportId));
        }

        // validate ReportView To Get Comparison
        $this->validateReportViewToGetOverviewData($reportView);

        // get startDate, endDate
        $startDateStr = $request->request->get('startDate');
        $endDateStr = $request->request->get('endDate');
        $startDate = date_create_from_format('Y-m-d', $startDateStr);
        $endDate = date_create_from_format('Y-m-d', $endDateStr);
        if (!$startDate instanceof DateTime || !$endDate instanceof DateTime) {
            throw new BadRequestHttpException('Expected startDate, endDate format is Y-m-d');
        }

        if ($startDate > $endDate) {
            throw new BadRequestHttpException('Expected startDate is before endDate');
        }

        /* get date field from report view. This is used for filter and draw chart in UI */
        $dateFieldAndFormat = $this->getDateFieldFromReportViewForDashboard($reportView);
        if (!$dateFieldAndFormat || !array_key_exists('field', $dateFieldAndFormat) || !array_key_exists('format', $dateFieldAndFormat)) {
            throw new BadRequestHttpException(sprintf('Could not get date field from master report #%d', $masterReportId));
        }

        $dateField = $dateFieldAndFormat['field'];

        /* build params to get report data for current and history */
        // build common params
        $requestParams = [
            ParamsBuilder::START_DATE => $startDateStr,
            ParamsBuilder::END_DATE => $endDateStr,
            ParamsBuilder::SORT_FIELD_KEY => $dateField,
            ParamsBuilder::ORDER_BY_KEY => 'asc'
        ];

        $params = $this->get('ur.services.report.params_builder')->buildFromReportViewAndParamsForDashboard($reportView, $requestParams);

        /* get and return report */
        // get reports
        $result = $this->getReport($params);

        // unset not need fields from reports
        $result = $this->getMinimizeReportForComparison($result->toArray());

        // do sum for same date in reports detail
        $reportViewFormatter = $this->get('ur.services.report.report_formatter');
        $result = $this->doSumForSameDate($result, $dateField, $params->getFormats(), $reportViewFormatter);

        // add dateField
        $result['dateField'] = $dateFieldAndFormat;

        return $result;
    }

    /**
     * @Rest\Post("/reportview/topperformers")
     *
     * @Rest\RequestParam(name="masterReport", nullable=false, description="the master report id")
     * @Rest\RequestParam(name="startDate", nullable=false, description="the start date, format YYYY-MM-DD")
     * @Rest\RequestParam(name="endDate", nullable=false, description="the end date, format YYYY-MM-DD")
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters = {
     *      {"name"="masterReport", "dataType"="number", "required"=true, "description"="the master report id"},
     *      {"name"="startDate", "dataType"="string", "required"=true, "description"="the start date, format YYYY-MM-DD"},
     *      {"name"="endDate", "dataType"="string", "required"=true, "description"="the end date, format YYYY-MM-DD"},
     *  }
     * )
     *
     * @param Request $request
     * @return array
     */
    public function reportTopPerformersAction(Request $request)
    {
        /* get params from request */
        // get master report id
        $masterReportId = $request->request->get('masterReport');
        $masterReportId = filter_var($masterReportId, FILTER_VALIDATE_INT);
        if (false === $masterReportId || $masterReportId < 0) {
            throw new BadRequestHttpException('Expected masterReport is positive integer');
        }

        // get report view by master report id and also check permission
        $reportView = $this->one($masterReportId);
        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException(sprintf('Report view #%s does not existing', $masterReportId));
        }

        // get comparison type
        $startDateStr = $request->request->get('startDate');
        $endDateStr = $request->request->get('endDate');
        if (empty($startDateStr) || empty($endDateStr)) {
            throw new BadRequestHttpException('Expected startDate, endDate not empty');
        }

        try {
            new \DateTime($startDateStr);
            new \DateTime($endDateStr);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Invalid startDate, endDate format, expected format YYYY-MM-DD', $masterReportId));
        }

        /* build params to get report data for top performers */
        $requestParams = [
            ParamsBuilder::START_DATE => $startDateStr, // override date range
            ParamsBuilder::END_DATE => $endDateStr, // override date range
            ParamsBuilder::PAGE_KEY => 1,
            ParamsBuilder::LIMIT_KEY => 10,
            ParamsBuilder::ORDER_BY_KEY => 'desc',
            ParamsBuilder::SORT_FIELD_KEY => $this->getDefaultSortFieldForReportView($reportView)
        ];

        $params = $this->get('ur.services.report.params_builder')->buildFromReportViewAndParams($reportView, $requestParams);

        /* get and return report */
        $result = $this->getReport($params)->toArray();

        // add date Field Formats for correct sorting in top screen
        $result['dateFieldFormats'] = $this->getDateFieldFormatsFromReportView($reportView);

        return $result;
    }

    /**
     * @Rest\Post("/reportview/{id}/data")
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
        //get request params
        $data = $request->request->all();

        $reportViewRepository = $this->get('ur.repository.report_view');

        // get report view and also check permission
        $reportViewData = $this->one($id);

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
        //check permission
        if ($params->getReportViewId() !== null) {
            $reportViewRepository = $this->get('ur.repository.report_view');
            $this->one($params->getReportViewId());
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
        header('Access-Control-Allow-Origin: *'); // manually allow cors when remove xdomain lib

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

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'reportview';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        // TODO: Implement getGETRouteName() method.
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.report_view');
    }

    /**
     * @param ParamsInterface $params
     * @return mixed|ReportResultInterface
     */
    private function getReport(ParamsInterface $params)
    {
        $result = $this->get('ur.services.report.report_builder')->getReport($params);
        $result->generateReports();

        return $result;
    }
}
