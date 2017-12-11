<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\Model\Core\ReportViewInterface;

class MigrateLargeReportViewCommand extends ContainerAwareCommand
{
    use LargeReportViewUtilTrait;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:large-report-view')
            ->setDescription('Fire jobs maintaining pre calculate table for large reports. Run if changing ur.report_view.large_threshold');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $reportViewManager = $container->get('ur.domain_manager.report_view');
        $manager = $container->get('ur.worker.manager');
        $largeThreshold = $container->getParameter('ur.report_view.large_threshold');
        $io = new SymfonyStyle($input, $output);

        $reportViews = $reportViewManager->all();

        $count = 0;
        /** @var ReportViewInterface $singleView */
        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $io->section(sprintf("Estimate size of report %s, id %s", $reportView->getName(), $reportView->getId()));

            if (!$this->isLargeReportView($reportView, $largeThreshold)) {
                $io->text("Not large report. Skip check");

                $reportView->setSmallReport();
                $reportViewManager->save($reportView);

                continue;
            }

            if (!empty($reportView->getPreCalculateTable())) {
                $io->text("Large report. But Pre Calculate success. Skip check");
                continue;
            }

            $io->success(sprintf("Large report. Create worker jobs Pre Calculate for report %s, id %s", $reportView->getName(), $reportView->getId()));
            $manager->maintainPreCalculateTableForLargeReportView($reportView->getId());
            $count++;
        }

        $io->success(sprintf('Create %d jobs update large report views. Please run worker!', $count));
    }
}