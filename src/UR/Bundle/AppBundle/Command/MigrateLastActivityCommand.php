<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ReportViewInterface;

class MigrateLastActivityCommand extends ContainerAwareCommand
{
    const FIELD_LAST_ACTIVITY = 'last_activity';
    const FIELD_LAST_RUN = 'last_run';

    const TABLE_DATA_SET = 'core_data_set';
    const TABLE_DATA_SOURCE = 'core_data_source';
    const TABLE_CONNECTED_DATA_SOURCE = 'core_connected_data_source';
    const TABLE_REPORT_VIEW = 'core_report_view';
    /**
     * @var Logger
     */
    private $logger;

    /** @var array */
    private $updateSQLs;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:last-activity')
            ->setDescription('Migrate to update `Last Activity` by old created date');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');

        $output->writeln('Starting command update last activity...');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $conn = $entityManager->getConnection();

        $this->updateSQLs = [];

        $dataSetManager = $container->get('ur.domain_manager.data_set');
        $dataSets = $dataSetManager->all();
        foreach ($dataSets as $dataSet) {
            $this->addLastActivityOnDataSet($dataSet);
        }

        $dataSourceManager = $container->get('ur.domain_manager.data_source');
        $dataSources = $dataSourceManager->all();
        foreach ($dataSources as $dataSource) {
            $this->addLastActivityOnDataSource($dataSource);
        }

        $connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');
        $connectedDataSources = $connectedDataSourceManager->all();
        foreach ($connectedDataSources as $connectedDataSource) {
            $this->addLastActivityOnConnectedDataSource($connectedDataSource);
        }

        $reportViewManager = $container->get('ur.domain_manager.report_view');
        $reportViews = $reportViewManager->all();
        foreach ($reportViews as $reportView) {
            $this->addLastActivityOnReportView($reportView);
        }

        foreach ($this->updateSQLs as $updateSQL) {
            try {
                $conn->exec($updateSQL);
            } catch (\Exception $e) {
                $output->writeln('Command run failed. Exception: ' . $e->getMessage());
            }
        }

        $output->writeln('Command run successfully');
    }

    /**
     * @param DataSetInterface $dataSet
     */
    private function addLastActivityOnDataSet(DataSetInterface $dataSet)
    {
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $this->buildSQLUpdateField(self::TABLE_DATA_SET, self::FIELD_LAST_ACTIVITY, $dataSet->getCreatedDate()->format('Y-m-d H:i:s'), $dataSet->getId());
    }

    /**
     * @param DataSourceInterface $dataSource
     */
    private function addLastActivityOnDataSource(DataSourceInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $this->buildSQLUpdateField(self::TABLE_DATA_SOURCE, self::FIELD_LAST_ACTIVITY, date('Y-m-d H:i:s'), $dataSource->getId());
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     */
    private function addLastActivityOnConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $this->buildSQLUpdateField(self::TABLE_CONNECTED_DATA_SOURCE, self::FIELD_LAST_ACTIVITY, date('Y-m-d H:i:s'), $connectedDataSource->getId());
    }

    /**
     * @param ReportViewInterface $reportView
     */
    private function addLastActivityOnReportView(ReportViewInterface $reportView)
    {
        if (!$reportView instanceof ReportViewInterface) {
            return;
        }

        $this->buildSQLUpdateField(self::TABLE_REPORT_VIEW, self::FIELD_LAST_ACTIVITY, $reportView->getCreatedDate()->format('Y-m-d H:i:s'), $reportView->getId());
        $this->buildSQLUpdateField(self::TABLE_REPORT_VIEW, self::FIELD_LAST_RUN, $reportView->getCreatedDate()->format('Y-m-d H:i:s'), $reportView->getId());
    }

    /**
     * @param string $tableName
     * @param string $fieldName
     * @param mixed $value
     * @param int $id
     */
    private function buildSQLUpdateField($tableName, $fieldName, $value, $id)
    {
        $this->updateSQLs[] = sprintf('UPDATE `%s` SET `%s` = "%s" WHERE `id` = %s', $tableName, $fieldName, $value, (int)$id);
    }
}