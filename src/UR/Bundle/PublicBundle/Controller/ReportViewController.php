<?php

namespace UR\Bundle\PublicBundle\Controller;

use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\PublicSimpleException;

/**
 * Class ReportController
 * @package UR\Bundle\ReportApiBundle\Controller
 */
class ReportViewController extends FOSRestController
{
    use ColumnUtilTrait;

    /**
     * Get shared report
     *
     * @Rest\Get("/reportviews/{id}/sharedReports")
     * @Rest\View(serializerGroups={"report_view.share", "report_view_multi_view.share", "report_view_data_set.share"})
     *
     * @Rest\QueryParam(name="token", nullable=false)
     * @Rest\QueryParam(name="startDate", nullable=true)
     * @Rest\QueryParam(name="endDate", nullable=true)
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searches", nullable=true)
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
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
     * @throws Exception
     */
    public function getSharedReportsAction(Request $request, $id)
    {
        $reportView = $this->get('ur.domain_manager.report_view')->find($id);
        if (!($reportView instanceof ReportViewInterface)) {
            throw new BadRequestHttpException('Invalid ReportView');
        }

        $token = $request->query->get(ReportViewInterface::TOKEN, null);
        if (null == $token) {
            throw new BadRequestHttpException('Invalid token');
        }

        $sharedKeysConfig = $reportView->getSharedKeysConfig();
        if (!array_key_exists($token, $sharedKeysConfig)) {
            throw new BadRequestHttpException('Invalid token');
        }

        if (!$reportView->isAvailableToRun()) {
            throw new PublicSimpleException(sprintf("We're building report for you. Please wait 5 minutes and retry"));
        }

        $shareConfig = $sharedKeysConfig[$token];

        $fieldsToBeShared = $shareConfig[ReportViewInterface::SHARE_FIELDS];
        $allowDatesOutside = array_key_exists(ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE, $shareConfig) ? $shareConfig[ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE] : false;
        $paginationParams = $request->query->all();
        $params = $this->getParams($reportView, $fieldsToBeShared, $paginationParams);
        $params->setNeedFormat(false);

        // get dateRange from config then convert to array such as [ startDate => '', endDate => '' ],
        // this is for return to UI for setting startDate-endDate for datePicker
        $dateRange = null;

        // dateRange from config may be dynamic date range (today, yesterday, last 7 days, ...)
        // or fixed date range as { startDate:2017-08-01, endDate:2017-08-18 }
        $dateRangeFromConfig = (array_key_exists(ReportViewInterface::SHARE_DATE_RANGE, $shareConfig)) ? $shareConfig[ReportViewInterface::SHARE_DATE_RANGE] : null;

        //// if: dynamic DateRange
        if (is_string($dateRangeFromConfig)) {
            if (!in_array($dateRangeFromConfig, DateFilter::$SUPPORTED_DATE_DYNAMIC_VALUES)) {
                throw new RuntimeException(sprintf('Invalid dateRange (%s) in shareable report config', $dateRangeFromConfig));
            }

            $dynamicDateRange = DateFilter::getDynamicDate(DateFilter::DATE_TYPE_DYNAMIC, $dateRangeFromConfig);
            $dateRange = ['startDate' => $dynamicDateRange[0], 'endDate' => $dynamicDateRange[1]];
        }

        //// else: fixed DateRange
        if (
            is_array($dateRangeFromConfig) &&
            !empty($dateRangeFromConfig) &&
            array_key_exists('startDate', $dateRangeFromConfig) && !empty($dateRangeFromConfig['startDate']) &&
            array_key_exists('endDate', $dateRangeFromConfig) && !empty($dateRangeFromConfig['endDate'])
        ) {
            // get dateRange from config such as [ startDate => '', endDate => '' ],
            $dateRange = $dateRangeFromConfig;
        }

        // if allowDatesOutside: max endDate is yesterday
        $yesterday = (new \DateTime('yesterday'))->setTime(23, 59, 59);
        if ($allowDatesOutside && date_create_from_format('Y-m-d', $dateRange['endDate']) > $yesterday) {
            $dateRange['endDate'] = $yesterday->format('Y-m-d');
        }

        // override dateRange if use user provided date range
        if (($params->getStartDate() instanceof \DateTime && $params->getEndDate() instanceof \DateTime)) {
            // validate custom date range
            $startDateFromConfig = new \DateTime($dateRange['startDate']);
            $endDateFromConfig = new \DateTime($dateRange['endDate']);

            $userProvidedStartDate = $params->getStartDate();
            $userProvidedEndDate = $params->getEndDate();

            // if not allowDatesOutside: do not allow user provided date is outside of shared date range
            if (!$allowDatesOutside
                && (
                    $userProvidedStartDate < $startDateFromConfig
                    || $userProvidedEndDate > $endDateFromConfig
                    || $userProvidedStartDate > $userProvidedEndDate
                )
            ) {
                throw new InvalidArgumentException(
                    sprintf('User provided startDate/endDate must be in report view date range (%s - %s)' .
                        ' and startDate must be not greater than endDate.',
                        $dateRange['startDate'],
                        $dateRange['endDate']
                    )
                );
            }

            // else, if allowDatesOutside: allow user provided date is outside of shared date range
            // but can't not over yesterday
            if ($allowDatesOutside
                && (
                    $userProvidedStartDate > $userProvidedEndDate
                    || $userProvidedEndDate > $yesterday
                )
            ) {
                throw new InvalidArgumentException(
                    sprintf('User provided endDate could not be greater than %s (yesterday)' .
                        ' and startDate must be not greater than endDate.',
                        $yesterday->format('Y-m-d')
                    )
                );
            }
        } else if ($dateRange && array_key_exists('startDate', $dateRange) && array_key_exists('endDate', $dateRange)){
            // override previous date range which is parsed from query params
            $params->setStartDate(new \DateTime($dateRange['startDate']));
            $params->setEndDate(new \DateTime($dateRange['endDate']));
        }

        $reportResult = $this->getReportBuilder()->getShareableReport($params, $fieldsToBeShared);
        $reportResult->generateReports();
        $report = $reportResult->toArray();

        $report['reportView'] = $reportView;

        // also return user provided dimensions, metrics, columns
        $report['dateRange'] = $dateRange;
        $report[ReportViewInterface::SHARE_FIELDS] = $shareConfig[ReportViewInterface::SHARE_FIELDS];

        // also return allowDatesOutside
        $report[ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE] = $allowDatesOutside;

        //// columns
        if (!is_array($report['columns']) || empty($report['columns'])) {
            $columns = array_merge($reportView->getDimensions(), $reportView->getMetrics());
            $mappedColumns = [];
            foreach ($columns as $index => $column) {
                if (!in_array($column, $report[ReportViewInterface::SHARE_FIELDS])) {
                    // do not return the columns that are not in shared fields
                    continue;
                }

                $mappedColumns[$column] = $this->convertColumn($column, $reportView->getIsShowDataSetName());
            }

            $report['columns'] = $mappedColumns;
        }

        return $report;
    }

    /**
     * @param ReportViewInterface $reportView
     * @param array $fieldsToBeShared
     * @param array $paginationParams
     * @return ParamsInterface formatted as:
     * {
     * {"name"="dimensions", "dataType"="array", "required"=false, "description"="list of dimensions to build report"},
     * {"name"="metrics", "dataType"="array", "required"=false, "description"="list of metrics to build report"},
     * {"name"="dataSets", "dataType"="array", "required"=false, "description"="list of data set id to build report"},
     * {"name"="fieldTypes", "dataType"="array", "required"=false, "description"="list of fields accompanied with their corresponding type"},
     * {"name"="joinBy", "dataType"="string", "required"=false, "description"="filter descriptor"},
     * {"name"="transforms", "dataType"="string", "required"=false, "description"="transform descriptor"},
     * {"name"="weightedCalculations", "dataType"="string", "required"=false, "description"="weighted value calculations descriptor"},
     * {"name"="filters", "dataType"="string", "required"=false, "description"="filters descriptor for multi view report"},
     * {"name"="multiView", "dataType"="string", "required"=false, "description"="specify the current report is a multi view report"},
     * {"name"="reportViews", "dataType"="string", "required"=false, "description"="report views descriptor"},
     * {"name"="showInTotal", "dataType"="string", "required"=false, "description"="those fields that are allowed to be shown in Total area"},
     * {"name"="formats", "dataType"="string", "required"=false, "description"="format descriptor"},
     * {"name"="subReportsIncluded", "dataType"="bool", "required"=false, "description"="include sub reports in multi view report"}
     * }
     * @see UR\Bundle\ReportApiBundle\Controller\ReportController
     */
    protected function getParams($reportView, array $fieldsToBeShared, array $paginationParams)
    {
        return $this->get('ur.services.report.params_builder')->buildFromReportViewForSharedReport($reportView, $fieldsToBeShared, $paginationParams);
    }

    protected function getReportBuilder()
    {
        return $this->get('ur.services.report.report_builder');
    }

    /**
     * @inheritdoc
     */
    protected function getDataSetManager()
    {
        return $this->get('ur.domain_manager.data_set');
    }
}