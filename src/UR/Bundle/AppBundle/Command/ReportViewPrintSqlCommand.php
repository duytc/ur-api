<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\ReportViewInterface;

class ReportViewPrintSqlCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = "ur:report-view:print-sql";
    const REPORT_VIEW_ID = 'reportViewId';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(self::REPORT_VIEW_ID, InputArgument::REQUIRED, 'Report view id')
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

        /** Get reports */
        $reportSelector = $container->get('ur.services.report.report_selector');
        $temporarySql = $reportSelector->getFullSQL($params);

        /** Print SQL */
        $io->section("SQL for creating temporary table and return data");
        $io->text($temporarySql);
        $io->success('Command run successfully. Quit command');
    }
}