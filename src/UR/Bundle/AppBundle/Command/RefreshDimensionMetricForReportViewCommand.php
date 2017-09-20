<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\ReportViewInterface;

class RefreshDimensionMetricForReportViewCommand extends ContainerAwareCommand
{
    use CalculateMetricsAndDimensionsTrait;

    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('tc:ur:report-view:refresh-dimension-metric')
            ->setDescription('Refresh dimensions and metrics for report view');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reportViewRepository = $this->getContainer()->get('ur.repository.report_view');
        $reportViewManager = $this->getContainer()->get('ur.domain_manager.report_view');
        $paramsBuilder = $this->getContainer()->get('ur.services.report.params_builder');
        $reportViews = $reportViewRepository->findBy(array('multiView' => false));

        $progressBar = new ProgressBar($output, count($reportViews));
        $progressBar->start();

        $count = 0;
        /** @var ReportViewInterface $reportView */
        foreach ($reportViews as $reportView) {
            $progressBar->advance();
            $metrics = $reportView->getMetrics();
            $dimensions = $reportView->getDimensions();
            if (count(array_intersect($metrics, $dimensions)) < 1) {
                continue;
            }

            $param = $paramsBuilder->buildFromReportView($reportView);
            $columns = $this->getMetricsAndDimensionsForSingleView($param);
            $reportView->setMetrics($columns[self::METRICS_KEY]);
            $reportView->setDimensions($columns[self::DIMENSIONS_KEY]);
            $reportViewManager->save($reportView);
            $count++;
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>%d report view get updated</info>', $count));
    }

    protected function getMetricsKey()
    {
        return self::METRICS_KEY;
    }

    protected function getDimensionsKey()
    {
        return self::DIMENSIONS_KEY;
    }
}