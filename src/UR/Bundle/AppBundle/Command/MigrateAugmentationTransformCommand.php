<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class MigrateAugmentationTransformCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:migrate:connected-data-source:augmentation-transform')
            ->setDescription('Migrate all Augmentation transforms, supporting multiple map conditions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connectedDataSourceManager = $this->getContainer()->get('ur.domain_manager.connected_data_source');
        $allConnectedDataSources = $connectedDataSourceManager->all();

        $count = 0;
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($allConnectedDataSources as $connectedDataSource) {
            $transforms = $connectedDataSource->getTransforms();
            $changed = false;
            foreach ($transforms as &$transform) {
                if (!array_key_exists('type', $transform)) {
                    continue;
                }

                if ($transform['type'] != CollectionTransformerInterface::AUGMENTATION) {
                    continue;
                }

                if (!array_key_exists('mapCondition', $transform)) {
                    continue;
                }

                $transform['mapConditions'] = [$transform['mapCondition']];

                unset($transform['mapCondition']);
                $changed = true;
            }

            if ($changed) {
                $connectedDataSource->setTransforms($transforms);
                $connectedDataSourceManager->save($connectedDataSource);
                $count++;
            }
        }

        $output->writeln(sprintf('DONE ! %d connected data sources get updated', $count));
    }
}