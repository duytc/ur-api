<?php
namespace UR\Service\Report;


use UR\Behaviors\ReportViewUtilTrait;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Model\Core\ReportViewInterface;

class ShareableLinkUpdater implements ShareableLinkUpdaterInterface
{
    use ReportViewUtilTrait;
    const TRANSFORM_FOR_AUTOMATED_SHARE_FIELDS = [
        AddCalculatedFieldTransform::TRANSFORMS_TYPE
    ];

    /**
     * @inheritdoc
     */
    public function updateShareableLinks(ReportViewInterface $reportView)
    {
        $sharedKeysConfig = $reportView->getSharedKeysConfig();

        if (empty($sharedKeysConfig)) {
            return;
        }

        $dateFilters = $this->getDateFiltersFromReportView($reportView);

        $dateRange = null;
        $startDate = null;
        $endDate = null;
        $userProvided = false;

        foreach ($dateFilters as $dateFilter) {
            if (!array_key_exists(DateFilter::DATE_USER_PROVIDED_FILTER_KEY, $dateFilter) ||
                !array_key_exists(DateFilter::DATE_TYPE_FILTER_KEY, $dateFilter) ||
                !array_key_exists(DateFilter::DATE_VALUE_FILTER_KEY, $dateFilter)
            ) {
                continue;
            }

            $userProvided = $userProvided || $dateFilter[DateFilter::DATE_USER_PROVIDED_FILTER_KEY];
            $dateType = $dateFilter[DateFilter::DATE_TYPE_FILTER_KEY];
            $dateValue = $dateFilter[DateFilter::DATE_VALUE_FILTER_KEY];

            if ($dateType == DateFilter::DATE_TYPE_DYNAMIC) {
                $dateRange = DateFilter::getTheLargestDynamicDate($dateRange, $dateValue);
            } else {
                /** Do nothing for fix date range. Date range for fix date range only change by human in UI. Not automatically */
                if (!array_key_exists(DateFilter::DATE_VALUE_FILTER_START_DATE_KEY, $dateValue) ||
                    !array_key_exists(DateFilter::DATE_VALUE_FILTER_END_DATE_KEY, $dateValue)
                ) {
                    continue;
                }
                $date = DateFilter::getTheLargestFixDate(
                    [$startDate, $endDate],
                    [$dateValue[DateFilter::DATE_VALUE_FILTER_START_DATE_KEY], $dateValue[DateFilter::DATE_VALUE_FILTER_END_DATE_KEY]]
                );

                $startDate = $date[0];
                $endDate = $date[1];
            }
        }

        $automatedShareFields = $this->getAutomatedShareFields($reportView);

        foreach ($sharedKeysConfig as &$config) {
            /** Update shared fields */
            $oldShareFields = $config[ReportViewInterface::SHARE_FIELDS];
            $shareFields = $this->updateShareFields($reportView, $oldShareFields);
            $shareFields = $this->addAutomatedShareFields($automatedShareFields, $shareFields);
            $config[ReportViewInterface::SHARE_FIELDS] = $shareFields;

            $config[ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE] = $userProvided && !$reportView->isLargeReport();

            if (!empty($dateRange)) {
                /** For dynamic filter date */
                $config[ReportViewInterface::SHARE_DATE_RANGE] = $dateRange;
            } else {
                /** For fix filter date */
                $config[ReportViewInterface::SHARE_DATE_RANGE] = [];
                $config[ReportViewInterface::SHARE_DATE_RANGE][DateFilter::DATE_VALUE_FILTER_START_DATE_KEY] = $startDate;
                $config[ReportViewInterface::SHARE_DATE_RANGE][DateFilter::DATE_VALUE_FILTER_END_DATE_KEY] = $endDate;
            }
        }

        $reportView->setSharedKeysConfig($sharedKeysConfig);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    private function getDateFiltersFromReportView(ReportViewInterface $reportView)
    {
        $filters = $this->getFiltersFromReportView($reportView);

        /** Get only date filters */
        $filters = array_filter($filters, function ($filter) {
            if (!array_key_exists(DateFilter::FIELD_TYPE_FILTER_KEY, $filter)) {
                return false;
            }

            if ($filter[DateFilter::FIELD_TYPE_FILTER_KEY] == AbstractFilter::TYPE_DATE) {
                return true;
            }

            return false;
        });

        return $filters;
    }

    /**
     * When deleting a field on report view - we delete this field on shareable links
     * When add new field on report view - we do nothing, keep current status of shareable links
     *
     * @param ReportViewInterface $reportView
     * @param array $oldShareFields
     * @return array
     */
    private function updateShareFields(ReportViewInterface $reportView, array $oldShareFields)
    {
        $reportViewFields = $this->getFieldsFromReportView($reportView);

        foreach ($oldShareFields as $key => $shareField) {
            if (!in_array($shareField, $reportViewFields)) {
                unset($oldShareFields[$key]);
            }
        }

        return array_values($oldShareFields);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    private function getAutomatedShareFields(ReportViewInterface $reportView)
    {
        $transforms = $reportView->getTransforms();
        $filterTransforms = array_filter($transforms, function ($transform) {
            return isset($transform['type']) && in_array($transform['type'], self::TRANSFORM_FOR_AUTOMATED_SHARE_FIELDS);
        });

        $addFields = [];
        foreach ($filterTransforms as $transform) {
            if (!is_array($transform) || !array_key_exists('fields', $transform)) {
                continue;
            }

            $groups = array_filter($transform['fields'], function ($group) {
                return isset($group['autoAddShareableReport']) && $group['autoAddShareableReport'] == true;
            });

            $groups = array_map(function ($group) {
                if (isset($group['field'])) {
                    return $group['field'];
                }
            }, $groups);

            $addFields = array_merge($addFields, $groups);
        }

        return array_values($addFields);
    }

    /**
     * @param $automatedShareFields
     * @param $shareFields
     * @return array
     */
    private function addAutomatedShareFields(array $automatedShareFields, array $shareFields)
    {
        return array_values(array_unique(array_merge($automatedShareFields, $shareFields)));
    }
}