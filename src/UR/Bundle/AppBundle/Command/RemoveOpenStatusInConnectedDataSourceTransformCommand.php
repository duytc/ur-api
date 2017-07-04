<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;

class RemoveOpenStatusInConnectedDataSourceTransformCommand extends ContainerAwareCommand
{
    const OPEN_STATUS = 'openStatus';

    /** @var  ReportViewManagerInterface */
    protected $connectedDataSourceManager;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:connected-data-source:remove-open-status-in-transform')
            ->setDescription('Open status need to be removed from transform json in connected data sources');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->notice('starting command...');

        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');
        $connectedDataSources = $this->connectedDataSourceManager->all();

        foreach ($connectedDataSources as &$connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }
            $transforms = $connectedDataSource->getTransforms();

            if (!is_array($transforms) || count($transforms) < 1) {
                continue;
            }

            foreach ($transforms as &$transform) {
                if (!array_key_exists(self::OPEN_STATUS, $transform)) {
                    continue;
                }

                unset($transform[self::OPEN_STATUS]);
            }

            $connectedDataSource->setTransforms($transforms);
            $this->connectedDataSourceManager->save($connectedDataSource);
        }
        $this->logger->notice(sprintf('removing all open status of %s connected data sources', count($connectedDataSources)));
        $this->logger->notice(sprintf('command run successfully'));
    }

}