<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class UpdateSubsetGroupKeyCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:transforms:subset-group:update-key')
            ->setDescription('Update subset group transform with new keys');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connectedDataSourceManager = $this->getContainer()->get('ur.domain_manager.connected_data_source');
        $connectedDataSources = $connectedDataSourceManager->all();
        $connectedDataSourceRepository = $this->getContainer()->get('ur.repository.connected_data_source');

        /** @var ConnectedDataSourceInterface $connectedDataSource */
        foreach($connectedDataSources as $connectedDataSource) {
            $transforms = $connectedDataSource->getTransforms();
            $count = 0;
            foreach($transforms as &$transform) {
                if ($transform[CollectionTransformerInterface::TYPE_KEY] === 'subset-group') {
                    $transform[CollectionTransformerInterface::TYPE_KEY] = 'subsetGroup';
                    $count++;
                }
            }

            if ($count > 0) {
                $connectedDataSourceRepository->updateTransforms($connectedDataSource, $transforms);
                $output->writeln(sprintf('%d transforms get updated', $count));
            }
        }
    }
}