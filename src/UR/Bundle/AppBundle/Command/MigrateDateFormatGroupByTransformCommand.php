<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class MigrateDateFormatGroupByTransformCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:migrate:date-format-group-transform')
            ->setDescription('Migrate DateFormat and GroupBy transform structure');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //migrate DateFormat in Connected Data Source
        $connectedDataSourceManager = $this->getContainer()->get('ur.domain_manager.connected_data_source');
        $reportViewManager = $this->getContainer()->get('ur.domain_manager.report_view');

        $connectedDataSources = $connectedDataSourceManager->all();

        $output->writeln('Start migrating DateFormat, GroupBy transform for Connected Data Source');
        $progress = new ProgressBar($output, count($connectedDataSources));
        $progress->start();
        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $progress->advance();
            $transforms = $connectedDataSource->getTransforms();
            $needToMigrate = false;
            foreach ($transforms as &$transform) {
                if (array_key_exists(CollectionTransformerInterface::TYPE_KEY, $transform) &&
                    ($transform[CollectionTransformerInterface::TYPE_KEY] == ColumnTransformerInterface::DATE_FORMAT || $transform[CollectionTransformerInterface::TYPE_KEY] == CollectionTransformerInterface::GROUP_BY)
                ) {
                    $transform[DateFormat::TIMEZONE_KEY] = 'UTC';
                    $needToMigrate = true;
                }
            }

            if ($needToMigrate) {
                $connectedDataSource->setTransforms($transforms);
                $connectedDataSourceManager->save($connectedDataSource);
            }
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('Migrating DateFormat for Connected Data Source done');
        $output->writeln('');

        $reportViews = $reportViewManager->all();

        $output->writeln('Start migrating GroupByTransform for Report View');
        $progress = new ProgressBar($output, count($reportViews));
        $progress->start();
        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $progress->advance();
            $transforms = $reportView->getTransforms();
            $needToMigrate = false;
            foreach ($transforms as &$transform) {
                if (array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform) &&
                    $transform[TransformInterface::TRANSFORM_TYPE_KEY] == TransformInterface::GROUP_TRANSFORM
                ) {
                    $transform[GroupByTransform::TIMEZONE_KEY] = 'UTC';
                    $needToMigrate = true;
                }
            }

            if ($needToMigrate) {
                $reportView->setTransforms($transforms);
                $reportViewManager->save($reportView);
            }
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('Migrating GroupByTransform for Report View done');
    }
}