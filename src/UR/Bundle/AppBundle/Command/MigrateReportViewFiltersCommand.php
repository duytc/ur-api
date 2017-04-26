<?php

namespace UR\Bundle\AppBundle\Command;


use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Service\StringUtilTrait;

class MigrateReportViewFiltersCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    const CURRENT_VERSION_UPDATE_USER_PROVIDED_DATE_RANGE = 1;

    private $currentVersion = self::CURRENT_VERSION_UPDATE_USER_PROVIDED_DATE_RANGE;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ReportViewManagerInterface
     */
    private $reportViewManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:report-view:filters')
            ->setDescription('Migrate Report View Filters to latest format');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->reportViewManager = $container->get('ur.domain_manager.report_view');

        $reportViews = $this->reportViewManager->all();

        $output->writeln(sprintf('Updating %d Report Views...', count($reportViews)));

        // migrate Report View filters
        $migratedAlertsCount = 0;

        switch ($this->currentVersion) {
            case self::CURRENT_VERSION_UPDATE_USER_PROVIDED_DATE_RANGE:
                $output->writeln(sprintf('Updating Filters for %d Report Views to latest format due to userProvided change...', count($reportViews)));
                $migratedAlertsCount = $this->migrateFiltersDueToUserProvidedChangeForReportView($output, $reportViews);

                break;
        }

        $output->writeln(sprintf('Command run successfully: %d Report Views.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|ReportViewInterface[] $reportViews
     * @return int migrated integrations count
     */
    private function migrateFiltersDueToUserProvidedChangeForReportView(OutputInterface $output, array $reportViews)
    {
        $migratedCount = 0;

        foreach ($reportViews as $reportView) {
            // migrate report view data sets
            /** @var Collection|ReportViewDataSetInterface[] $reportViewDataSets */
            $reportViewDataSets = $reportView->getReportViewDataSets();
            if ($reportViewDataSets instanceof Collection) {
                $reportViewDataSets = $reportViewDataSets->toArray();
            }

            $reportViewDataSets = $this->migrateFiltersDueToUserProvidedChangeForReportViewDataSets($output, $reportViewDataSets);
            $reportView->setReportViewDataSets($reportViewDataSets);

            // migrate report view multi views
            /** @var Collection|ReportViewMultiViewInterface[] $reportViewMultiViews */
            $reportViewMultiViews = $reportView->getReportViewMultiViews();
            if ($reportViewMultiViews instanceof Collection) {
                $reportViewMultiViews = $reportViewMultiViews->toArray();
            }

            $reportViewMultiViews = $this->migrateFiltersDueToUserProvidedChangeForReportViewMultiViews($output, $reportViewMultiViews);
            $reportView->setReportViewMultiViews($reportViewMultiViews);

            // update back to report view
            $migratedCount++;
            $this->reportViewManager->save($reportView);
        }

        return $migratedCount;
    }

    /**
     * migrate Filters Due To User Provide Change For Report View Data Sets
     *
     * @param OutputInterface $output
     * @param array|ReportViewDataSetInterface[] $reportViewDataSets
     * @return array|\UR\Model\Core\ReportViewDataSetInterface[]
     */
    private function migrateFiltersDueToUserProvidedChangeForReportViewDataSets(OutputInterface $output, array $reportViewDataSets)
    {
        foreach ($reportViewDataSets as &$reportViewDataSet) {
            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $filters = $reportViewDataSet->getFilters();
            if (!is_array($filters)) {
                continue;
            }

            $filters = $this->migrateFiltersDueToUserProvideChange($output, $filters);

            // set back to reportViewDataSet
            $reportViewDataSet->setFilters($filters);
        }

        return $reportViewDataSets;
    }

    /**
     * migrate Filters Due To User Provide Change For Report View Multi Views
     *
     * @param OutputInterface $output
     * @param array|ReportViewMultiViewInterface[] $reportViewMultiViews
     * @return array
     */
    private function migrateFiltersDueToUserProvidedChangeForReportViewMultiViews(OutputInterface $output, array $reportViewMultiViews)
    {
        foreach ($reportViewMultiViews as &$reportViewMultiView) {
            if (!$reportViewMultiView instanceof ReportViewMultiViewInterface) {
                continue;
            }

            $filters = $reportViewMultiView->getFilters();
            if (!is_array($filters)) {
                continue;
            }

            $filters = $this->migrateFiltersDueToUserProvideChange($output, $filters);

            // set back to reportViewDataSet
            $reportViewMultiView->setFilters($filters);
        }

        return $reportViewMultiViews;
    }

    /**
     * migrate Filters Due To User Provide Change for Report View
     *
     * @param OutputInterface $output
     * @param array $filters
     * @return array migrated filters
     */
    private function migrateFiltersDueToUserProvideChange(OutputInterface $output, array $filters)
    {
        /*
         * from format:
         * [
         *     {
         *        "field":"date",
         *        "type":"date",
         *        "format":null,
         *        "dateValue":{
         *           "startDate":"2016-04-27",
         *           "endDate":"2017-04-25"
         *        },
         *        "dateType":"userProvided" // dynamic, customRange or userProvided
         *     }
         *  ]
         */

        /*
         * to format:
         * [
         *     {
         *        "field":"date",
         *        "type":"date",
         *        "format":null,
         *        "dateValue":{
         *           "startDate":"2016-04-27",
         *           "endDate":"2017-04-25"
         *        },
         *        "userDefine":true,
         *        "dateType":"customRange" // dynamic, customRange or userDefine
         *     }
         *  ]
         */

        foreach ($filters as &$filter) {
            if (!is_array($filter)) {
                continue;
            }

            // sure filter is date filter
            if (!array_key_exists(DateFilter::FIELD_TYPE_FILTER_KEY, $filter)
                || $filter[DateFilter::FIELD_TYPE_FILTER_KEY] !== DateFilter::TYPE_DATE
            ) {
                continue;
            }

            // sure date filter has dateType
            if (!array_key_exists(DateFilter::DATE_TYPE_FILTER_KEY, $filter)) {
                continue;
            }

            $dateType = $filter[DateFilter::DATE_TYPE_FILTER_KEY];

            // if already has userProvided key and value is true
            if (array_key_exists(DateFilter::DATE_USER_PROVIDED_FILTER_KEY, $filter)
                && $filter[DateFilter::DATE_USER_PROVIDED_FILTER_KEY] === true
            ) {
                continue;
            }

            // add userProvided element
            if ($dateType !== 'userProvided') {
                $filter[DateFilter::DATE_USER_PROVIDED_FILTER_KEY] = false;
                continue;
            }

            // migrate for "userProvided"
            $dateType = DateFilter::DATE_TYPE_CUSTOM_RANGE;
            $filter[DateFilter::DATE_TYPE_FILTER_KEY] = $dateType;
            $filter[DateFilter::DATE_USER_PROVIDED_FILTER_KEY] = true;
        }

        return $filters;
    }
}