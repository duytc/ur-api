<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\FieldType;

class MigrateReportViewMetricsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:migrate:report-view:metrics')
            ->setDescription('Migrate report views, remove new fields from metrics');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reportViewManager = $this->getContainer()->get('ur.domain_manager.report_view');
        $paramBuilder = $this->getContainer()->get('ur.services.report.params_builder');
        $singleViews = $reportViewManager->getSingleViews();
        $count = [];
        /** @var ReportViewInterface $singleView */
        foreach ($singleViews as $singleView) {
            $transforms = $paramBuilder->createTransforms($singleView->getTransforms(), $metricCalculation);
            $newFields = [];
            $textAndDateFields = [];
            foreach ($transforms as $transform) {
                if ($transform instanceof NewFieldTransform) {
                    $newFields[] = $transform->getFieldName();
                }
            }

            $types = $singleView->getFieldTypes();
            foreach ($types as $field => $type) {
                if (!in_array($type, [FieldType::NUMBER, FieldType::DECIMAL])) {
                    $textAndDateFields[] = $field;
                }
            }

            $metrics = $singleView->getMetrics();
            $metrics = array_diff($metrics, $newFields);
            $metrics = array_diff($metrics, $textAndDateFields);
            $metrics = array_values($metrics);
            $singleView->setMetrics($metrics);
            $reportViewManager->save($singleView);
            if (array_key_exists($singleView->getId(), $count)) {
                $count[$singleView->getId()]++;
            } else {
                $count[$singleView->getId()] = 1;
            }
        }

        $output->writeln(sprintf('%d report views get updated!', count($count)));
    }
}