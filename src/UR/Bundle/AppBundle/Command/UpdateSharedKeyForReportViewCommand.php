<?php

namespace UR\Bundle\AppBundle\Command;


use Doctrine\Common\Collections\Collection;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportView;
use UR\Model\Core\ReportViewInterface;

class UpdateSharedKeyForReportViewCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:report-view:update-shared-key')
            ->setDescription('Update SharedKey for all report views if not yet had before');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        /** @var Logger $logger */
        $logger = $container->get('logger');
        /** @var ReportViewManagerInterface $reportViewManager */
        $reportViewManager = $container->get('ur.domain_manager.report_view');

        $logger->info('updating SharedKey for ReportViews...');

        /** @var Collection|ReportViewInterface[] $reportViews */
        $reportViews = $container->get('ur.domain_manager.report_view')->all();

        $updatedReportViews = 0;
        foreach ($reportViews as $reportView) {
            $sharedKeysConfig = $reportView->getSharedKeysConfig();

            if (null == $sharedKeysConfig || !is_string($sharedKeysConfig) || empty($sharedKeysConfig)) {
                $sharedKeysConfig = ReportView::generateSharedKey();
                $reportView->setSharedKeysConfig($sharedKeysConfig);
                $reportViewManager->save($reportView);
                $updatedReportViews++;
            }
        }

        $logger->info('updating SharedKey for ReportViews... successfully ' . $updatedReportViews . ' ReportViews');
    }
} 