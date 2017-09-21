<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;

class MigrateGroupByTransformCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:migrate:group-by-transform')
            ->setDescription('Migrate all Group transforms, both on connected data source and report view');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connectedDataSourceManager = $this->getContainer()->get('ur.domain_manager.connected_data_source');
        $reportViewManager = $this->getContainer()->get('ur.domain_manager.report_view');
        $allConnectedDataSources = $connectedDataSourceManager->all();

        $progressBar = new ProgressBar($output, count($allConnectedDataSources));
        $progressBar->start();
        $count = 0;
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($allConnectedDataSources as $connectedDataSource) {
            $transforms = $connectedDataSource->getTransforms();

            $hasGroup = false;
            foreach ($transforms as &$transform) {
                if (!array_key_exists('type', $transform)) {
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::SUBSET_GROUP) {
                    if (!array_key_exists(SubsetGroup::AGGREGATION_FIELDS_KEY, $transform)) {
                        $transform[SubsetGroup::AGGREGATION_FIELDS_KEY] = [];
                        $transform[SubsetGroup::AGGREGATE_ALL_KEY] = true;
                        continue;
                    }

                    if (!array_key_exists(SubsetGroup::AGGREGATE_ALL_KEY, $transform)) {
                        $fields = $transform[SubsetGroup::AGGREGATION_FIELDS_KEY];
                        if (empty($fields)) {
                            $transform[SubsetGroup::AGGREGATE_ALL_KEY] = true;
                        } else {
                            $transform[SubsetGroup::AGGREGATE_ALL_KEY] = false;
                        }
                    }

                    continue;
                }

                if ($transform['type'] != CollectionTransformerInterface::GROUP_BY) {
                    continue;
                }

                $hasGroup = true;

                $transform[GroupByColumns::AGGREGATION_FIELDS_KEY] = [];
                $transform[GroupByColumns::AGGREGATE_ALL_KEY] = true;
                unset($transform);
            }

            unset($transform);
            if ($hasGroup) {
                $connectedDataSource->setTransforms(array_values($transforms));
                $connectedDataSourceManager->save($connectedDataSource);
                $count++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>DONE ! %d connected data sources get updated</info>', $count));

        $reportViews = $reportViewManager->all();

        $progressBar = new ProgressBar($output, count($reportViews));
        $progressBar->start();
        $count = 0;
        /** @var ReportViewInterface $reportView */
        foreach ($reportViews as $reportView) {
            $transforms = $reportView->getTransforms();
            foreach ($transforms as $index => &$transform) {
                if (!array_key_exists('type', $transform)) {
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::AGGREGATION) {
                    unset($transforms[$index]);
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::POST_AGGREGATION) {
                    unset($transforms[$index]);
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::ADD_FIELD) {
                    $transform['isPostGroup'] = true;
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::ADD_CALCULATED_FIELD) {
                    $transform['isPostGroup'] = true;
                    continue;
                }

                if ($transform['type'] == CollectionTransformerInterface::COMPARISON_PERCENT) {
                    $transform['isPostGroup'] = true;
                    continue;
                }

                if ($transform['type'] = CollectionTransformerInterface::GROUP_BY) {
                    $transform[GroupByColumns::AGGREGATION_FIELDS_KEY] = [];
                    $transform[GroupByColumns::AGGREGATE_ALL_KEY] = true;
                    continue;
                }

                unset($transform);
            }

            $reportView->setTransforms(array_values($transforms));
            $reportViewManager->save($reportView);
            $count++;

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>DONE ! %d report view get updated</info>', $count));
    }
}