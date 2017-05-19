<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ReportViewManager;
use UR\Model\Core\ReportViewInterface;

class MigrateReportViewFormatFromObjectToArrayCommand extends ContainerAwareCommand
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ReportViewManager
     */
    private $reportViewManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:report-view:format')
            ->setDescription('Migrate report views, change format from object to json');
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

        $output->writeln(sprintf('Migrating %d report views', count($reportViews)));
        $migratedAlertsCount = $this->migrateFormatOfReportView($output, $reportViews);
        $output->writeln(sprintf('Command change successfully: %d report views. %s report views no need to update', $migratedAlertsCount, count($reportViews) - $migratedAlertsCount));
    }

    /**
     * migrate ReportViews's format from object to json
     * ReportViewMultiView do not have format, so no need to update
     *
     * @param OutputInterface $output
     * @param array|ReportViewInterface[] $reportViews
     * @return int migrated reportViews count
     */
    private function migrateFormatOfReportView(OutputInterface $output, array $reportViews)
    {
        $migratedCount = 0;

        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $formats = $reportView->getFormats();

            $newFormats = array_values($formats);

            if ($newFormats != $formats) {
                $migratedCount++;
                $reportView->setFormats($newFormats);
                $this->reportViewManager->save($reportView);
                $this->logger->info(sprintf('Update report view %s, with id %s', $reportView->getName(), $reportView->getId()));
            }
        }

        return $migratedCount;
    }
}