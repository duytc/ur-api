<?php

namespace UR\Bundle\PublicBundle\Controller;

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
use UR\Service\Report\ReportViewFormatter;

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
     */
    public function getSharedReportsAction(Request $request, $id)
    {
        $reportView = $this->get('ur.domain_manager.report_view')->find($id);
        if (!($reportView instanceof ReportViewInterface)) {
            throw new BadRequestHttpException('Invalid ReportView');
        }

        $token = $request->query->get('token', null);
        if (null == $token) {
            throw new BadRequestHttpException('Invalid token');
        }

        $sharedKeysConfig = $reportView->getSharedKeysConfig();
        if (!array_key_exists($token, $sharedKeysConfig)) {
            throw new BadRequestHttpException('Invalid token');
        }

        $config = $sharedKeysConfig[$token];

        $fieldsToBeShared = $config['fields'];
        $paginationParams = $request->query->all();
        $params = $this->getParams($reportView, $paginationParams);

        // get dateRange from config then convert to array such as [ startDate => '', endDate => '' ],
        // this is for return to UI for setting startDate-endDate for datePicker
        $dateRange = null;

        // dateRange from config may be dynamic date range (today, yesterday, last 7 days, ...)
        // or fixed date range as { startDate:2017-08-01, endDate:2017-08-18 }
        $dateRangeFromConfig = (array_key_exists('dateRange', $config)) ? $config['dateRange'] : null;
        if (is_string($dateRangeFromConfig)) {
            if (!in_array($dateRangeFromConfig, DateFilter::$SUPPORTED_DATE_DYNAMIC_VALUES)) {
                throw new RuntimeException(sprintf('Invalid dateRange (%s) in shareable report config', $dateRangeFromConfig));
            }

            $dynamicDateRange = DateFilter::getDynamicDate(DateFilter::DATE_TYPE_DYNAMIC, $dateRangeFromConfig);
            $dateRange = ['startDate' => $dynamicDateRange[0], 'endDate' => $dynamicDateRange[1]];
        }

        // else: fixedDateRange
        if (
            is_array($dateRangeFromConfig) &&
            !empty($dateRangeFromConfig) &&
            array_key_exists('startDate', $dateRangeFromConfig) && !empty($dateRangeFromConfig['startDate']) &&
            array_key_exists('endDate', $dateRangeFromConfig) && !empty($dateRangeFromConfig['endDate'])
        ) {
            // get dateRange from config such as [ startDate => '', endDate => '' ],
            $dateRange = $dateRangeFromConfig;

            // if use user provided date range
            if (($params->getStartDate() instanceof \DateTime && $params->getEndDate() instanceof \DateTime)) {
                // validate custom date range
                $reportViewStartDate = new \DateTime($dateRangeFromConfig['startDate']);
                $reportViewEndDate = new \DateTime($dateRangeFromConfig['endDate']);

                $userProvidedStartDate = $params->getStartDate();
                $userProvidedEndDate = $params->getEndDate();

                if ($userProvidedStartDate < $reportViewStartDate
                    || $userProvidedEndDate > $reportViewEndDate
                    || $userProvidedStartDate > $userProvidedEndDate
                ) {
                    throw new InvalidArgumentException(
                        sprintf('User provided startDate/endDate must be in report view date range (%s - %s)' .
                            ' and startDate must be not greater than endDate.',
                            $dateRangeFromConfig['startDate'],
                            $dateRangeFromConfig['endDate']
                        )
                    );
                }
            } else {
                // override previous date range which is parsed from query params
                $params->setStartDate(new \DateTime($dateRangeFromConfig['startDate']));
                $params->setEndDate(new \DateTime($dateRangeFromConfig['endDate']));

            }
        }

        $reportResult = $this->getReportBuilder()->getShareableReport($params, $fieldsToBeShared);
        $report = $reportResult->toArray();

        $report['reportView'] = $reportView;

        // also return user provided dimensions, metrics, columns
        $report['dateRange'] = $dateRange;
        $report['fields'] = $config['fields'];

        //// columns
        if (!is_array($report['columns']) || empty($report['columns'])) {
            $columns = array_merge($reportView->getDimensions(), $reportView->getMetrics());
            $mappedColumns = [];
            foreach ($columns as $index => $column) {
                if (!in_array($column, $report['fields'])) {
                    // do not return the columns that are not in shared fields
                    continue;
                }

                $mappedColumns[$column] = $this->convertColumn($column, $reportView->getIsShowDataSetName());
            }

            if ($reportView->isMultiView()) {
                $mappedColumns[ReportViewFormatter::REPORT_VIEW_ALIAS_KEY] = ReportViewFormatter::REPORT_VIEW_ALIAS_NAME;
            }

            $report['columns'] = $mappedColumns;
        }

        return $report;
    }

    /**
     * @param ReportViewInterface $reportView
     * @param array $paginationParams
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
    protected function getParams($reportView, array $paginationParams)
    {
        return $this->get('ur.services.report.params_builder')->buildFromReportViewForSharedReport($reportView, $paginationParams);
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