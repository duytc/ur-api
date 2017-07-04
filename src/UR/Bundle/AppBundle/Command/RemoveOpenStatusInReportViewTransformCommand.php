<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;

class RemoveOpenStatusInReportViewTransformCommand extends ContainerAwareCommand
{
    const OPEN_STATUS = 'openStatus';

    /** @var  ReportViewManagerInterface */
    protected $reportViewManager;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:report-view:remove-open-status-in-transform')
            ->setDescription('Open status need to be removed from transform json in report view');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->notice('starting command...');

        $this->reportViewManager = $container->get('ur.domain_manager.report_view');
        $reportViews = $this->reportViewManager->all();

        foreach ($reportViews as &$reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }
            $transforms = $reportView->getTransforms();

            if (!is_array($transforms) || count($transforms) < 1) {
                continue;
            }

            foreach ($transforms as &$transform) {
                if (!array_key_exists(self::OPEN_STATUS, $transform)) {
                    continue;
                }

                unset($transform[self::OPEN_STATUS]);
            }

            $reportView->setTransforms($transforms);
            $this->reportViewManager->save($reportView);
        }
        $this->logger->notice(sprintf('removing all open status of %s report views', count($reportViews)));
        $this->logger->notice(sprintf('command run successfully'));
    }

}