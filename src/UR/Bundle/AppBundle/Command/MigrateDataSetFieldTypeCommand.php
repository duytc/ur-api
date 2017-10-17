<?php

namespace UR\Bundle\AppBundle\Command;


use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\StringUtilTrait;

class MigrateDataSetFieldTypeCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

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
            ->setName('ur:migrate:data-set:field-type')
            ->setDescription('Migrate data set dimensions, metrics to latest format');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $this->logger = $container->get('logger');

        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->reportViewManager = $container->get('ur.domain_manager.report_view');

        $dataSets = $this->dataSetManager->all();

        $output->writeln(sprintf('updating %d Data Set to latest format', count($dataSets)));

        // migrate alert params
        $migratedDataSetsCount = $this->migrateDataSetDimensionsMetricsFieldType($output, $dataSets);

        $output->writeln(sprintf('command run successfully: %d Data Sets.', $migratedDataSetsCount));
    }

    /**
     * migrate data set dimensions metrics field type to latest format
     *
     * @param OutputInterface $output
     * @param array|DataSetInterface[] $dataSets
     * @return int migrated integrations count
     */
    private function migrateDataSetDimensionsMetricsFieldType(OutputInterface $output, array $dataSets)
    {
        $migratedCount = 0;

        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $migratedCount++;

            // migrate data set
            $this->migrateOneDataSet($dataSet);

            // migrate it's connected data sources
            $connectedDataSources = $dataSet->getConnectedDataSources();
            foreach ($connectedDataSources as $connectedDataSource) {
                $this->migrateOneConnectedDataSource($connectedDataSource);
            }

            // migrate report views that use this data set, also all related report views
            /** @var ReportViewInterface[] $reportViews */
            $reportViews = $this->reportViewManager->getReportViewsByDataSet($dataSet);
            foreach ($reportViews as $reportView) {
                $this->migrateOneReportView($reportView, $alsoRelatedReportViews = true);
            }
        }

        return $migratedCount;
    }

    /**
     * @param DataSetInterface $dataSet
     */
    private function migrateOneDataSet(DataSetInterface $dataSet)
    {
        /*
         * from 'multiLineText' to 'largeText'
         */

        // dimensions
        $dimensions = $dataSet->getDimensions();
        foreach ($dimensions as $dimension => &$dimensionType) {
            $dimensionType = $this->migrateType($dimensionType);
        }

        // metrics
        $metrics = $dataSet->getMetrics();
        foreach ($metrics as $metric => &$metricType) {
            $metricType = $this->migrateType($metricType);
        }

        $dataSet->setDimensions($dimensions);
        $dataSet->setMetrics($metrics);
        $this->dataSetManager->save($dataSet);
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     */
    private function migrateOneConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        // no need migrate
    }

    /**
     * @param ReportViewInterface $reportView
     * @param bool $alsoRelatedReportViews
     */
    private function migrateOneReportView(ReportViewInterface $reportView, $alsoRelatedReportViews = false)
    {
        /*
         * {
         *      "date_41":"date",
         *      "tag_41":"text",
         *      "__date_year_41":"number",
         *      "__date_month_41":"number",
         *      "__date_day_41":"number",
         *      "ad_requests_41":"number",
         *      "url":"multiLineText", // migrate to => "url":"largeText"
         *      ...
         * }
         */
        $fieldTypes = $reportView->getFieldTypes();
        foreach ($fieldTypes as $field => &$fieldType) {
            $fieldType = $this->migrateType($fieldType);
        }

        $reportView->setFieldTypes($fieldTypes);

        if (!$alsoRelatedReportViews) {
            $this->reportViewManager->save($reportView);

            return;
        }

        /** @var Collection|ReportViewDataSetInterface[] $reportViewDataSets */
        $reportViewDataSets = $reportView->getReportViewDataSets();
        if ($reportViewDataSets instanceof Collection) {
            $reportViewDataSets = $reportViewDataSets->toArray();
        }

        /** @var ReportViewInterface[] $relatedReportViewsByDataSet */
        $relatedReportViewsByDataSet = array_map(function (ReportViewDataSetInterface $reportViewDataSet) {
            return $reportViewDataSet->getReportView();
        }, $reportViewDataSets);

        foreach ($relatedReportViewsByDataSet as $relatedReportViewByDataSet) {
            // exclude current report view
            if ($relatedReportViewByDataSet->getId() === $reportView->getId()) {
                continue;
            }

            $this->migrateOneReportView($relatedReportViewByDataSet, $alsoRelatedReportViews = false);
        }

        // finally save report view
        $this->reportViewManager->save($reportView);
    }

    /**
     * @param $fieldType
     * @return string
     */
    private function migrateType($fieldType)
    {
        if ($fieldType === 'multiLineText') {
            $fieldType = 'largeText';
        }

        return $fieldType;
    }
}