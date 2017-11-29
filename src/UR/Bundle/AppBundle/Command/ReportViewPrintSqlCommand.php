<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\ReportViewInterface;

class ReportViewPrintSqlCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = "ur:report-view:print-sql";
    const REPORT_VIEW_ID = 'reportViewId';
    const SORT_FIELD_WITH_ID = 'sortField';
    const SORT_DIRECTION = 'orderBy';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(self::REPORT_VIEW_ID, InputArgument::REQUIRED, 'Report view id')
            ->addOption(self::SORT_FIELD_WITH_ID, "f", InputOption::VALUE_OPTIONAL, 'Sort field with id')
            ->addOption(self::SORT_DIRECTION, "o", InputOption::VALUE_OPTIONAL, 'Sort direction')
            ->setDescription('Print SQL query data for report view');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        /** Validate input */
        $reportViewManager = $container->get('ur.domain_manager.report_view');
        $reportViewId = $input->getArgument(self::REPORT_VIEW_ID);
        $reportView = $reportViewManager->find($reportViewId);

        if (!$reportView instanceof ReportViewInterface) {
            $io->error("Report View Not Available. Recheck report view id. Quit command");
            return;
        }

        /** Build params */
        $paramsBuilder = $container->get('ur.services.report.params_builder');
        $params = $paramsBuilder->buildFromReportView($reportView);

        if (!empty($input->getOption(self::SORT_FIELD_WITH_ID))) {
            $sortField = $input->getOption(self::SORT_FIELD_WITH_ID);
            $sortDirection = $input->getOption(self::SORT_DIRECTION);
            $sortDirection = empty($sortDirection) ? "asc" : $sortDirection;

            $params->setSortField($sortField);
            $params->setOrderBy($sortDirection);
        }

        /** Get reports */
        $reportSelector = $container->get('ur.services.report.report_selector');
        
        if (!empty($reportView->getPreCalculateTable())) {
            $temporarySql = $reportSelector->getFullSQLForPreCalculateTable($params, $reportView->getPreCalculateTable());
            $io->section("SQL for return data from Pre Calculate Table");
        } else {
            $temporarySql = $reportSelector->getFullSQL($params);
            $io->section("SQL for creating temporary table and return data");
        }

        /** Print SQL */
        $io->text($temporarySql);
        $io->success('Command run successfully. Quit command');
    }
}