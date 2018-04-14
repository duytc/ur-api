<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\StringUtilTrait;

class MigrateReportViewShowInTotalCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    const MIGRATE_REPORT_VIEWS_ALL = 'all';
    /**
     * @var ReportViewManagerInterface
     */
    private $reportViewManager;

    /** @var SymfonyStyle */
    private $io;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:report-view:show-in-total')
            ->setDescription('Migrating data on core_report_view table with show_in_total field')
            ->addArgument('reportViewId', InputArgument::OPTIONAL, 'report-view will be migrated ');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $this->io = new SymfonyStyle($input, $output);
        $container = $this->getContainer();
        $this->reportViewManager = $container->get('ur.domain_manager.report_view');
        $reportViewId = $input->getArgument('reportViewId');
        $migrateCount = $this->migrateShowInTotal($reportViewId);
        $this->io->success(sprintf('Command runs successfully: %d reports.', $migrateCount));
    }

    /**
     * @param $reportViewId
     * @return int
     */
    private function migrateShowInTotal($reportViewId)
    {
        $migrateCount = 0;
        if (empty($reportViewId)) {
            $reportViews = $this->reportViewManager->all();
        } else {
            if (!is_numeric($reportViewId)) {
                $this->io->text('<comment>Invalid reportViewId, expect reportViewId is a number. Please try again.</comment>');
                return 0;
            }
            $reportViewId = (int)$reportViewId;
            $reportView = $this->reportViewManager->find($reportViewId);
            if (!$reportView instanceof ReportViewInterface) {
                $this->io->text(sprintf('<comment>Not found report view with id = %d.</comment>', $reportViewId));
                return 0;
            }
            $reportViews[] = $reportView;
        }

        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $showInTotal = $reportView->getShowInTotal();
            if (!is_array($showInTotal) || empty($showInTotal)) {
                continue;
            }

            if (is_array(reset($showInTotal)) && array_key_exists('type', reset($showInTotal))) {
                $this->io->text(sprintf('<comment>This report view has already been migrated, no need to migrate report view id = %d</comment>', $reportView->getId()));
                continue;
            }
            $newShowInTotal[] = [
                'type' => 'aggregate',
                'fields' => $showInTotal
            ];

            $reportView->setShowInTotal($newShowInTotal);
            $this->reportViewManager->save($reportView);
            $migrateCount++;
            $this->io->text(sprintf('<info>Migrate for report view id = %d</info>', $reportView->getId()));

            // set = null for faster
            $newShowInTotal = null;
            $showInTotal = null;
            $reportView = null;
        }

        return $migrateCount;
    }
}