<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;

class UnlockReportViewCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    const COMMAND_NAME = 'ur:report-view:unlock';
    const ALL_PUBLISHERS = 'all-publishers';
    const PUBLISHER = 'enables-user';
    const REPORT_VIEWS = 'report-views';

    /** @var Logger */
    private $logger;

    /** @var  ReportViewManagerInterface */
    private $reportViewManager;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::ALL_PUBLISHERS, 'a', InputOption::VALUE_NONE,
                'Unlock for all users')
            ->addOption(self::PUBLISHER, 'u', InputOption::VALUE_OPTIONAL,
                'Unlock for special user')
            ->addOption(self::REPORT_VIEWS, 'r', InputOption::VALUE_OPTIONAL,
                'Unlock for special report views. Allow multiple report views, separated by comma. Example 15,17,29')
            ->setDescription('Unlock report view to allow editing');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->reportViewManager = $container->get('ur.domain_manager.report_view');
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $io = new SymfonyStyle($input, $output);

        $reportViews = $this->getReportViewsFromInput($input, $output);
        if (count($reportViews) < 1) {
            return;
        }

        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $io->section(sprintf("Unlock for report view %s, id = %s", $reportView->getName(), $reportView->getId()));
            $reportView->setAvailableToChange(true);
            $reportView->setAvailableToRun(true);
            $this->reportViewManager->save($reportView);
        }

        $io->success(sprintf("Unlock successfully for %s report views. Quit command", count($reportViews)));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    private function getReportViewsFromInput(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $allPublisher = $input->getOption(self::ALL_PUBLISHERS);
        $publisherId = $input->getOption(self::PUBLISHER);
        $reportViewIds = $input->getOption(self::REPORT_VIEWS);

        if (empty($allPublisher) &&
            empty($publisherId) &&
            empty($reportViewIds)
        ) {
            $io->warning('No report views found. Please recheck your input');

            return [];
        }

        if (!empty($allPublisher)) {
            return $this->reportViewManager->all();
        }

        if (!empty($publisherId)) {
            $publisher = $this->publisherManager->find($publisherId);
            if (!$publisher instanceof PublisherInterface) {
                $io->warning(sprintf('No report views found for publisher %s. Please recheck your input', $publisherId));

                return [];
            }

            $qb = $this->reportViewManager->getReportViewsForPublisherQuery($publisher);

            return $qb->getQuery()->getResult();
        }

        if (!empty($reportViewIds)) {
            $reportViewIds = explode(",", $reportViewIds);
            $reportViews = [];

            foreach ($reportViewIds as $reportViewId) {
                $reportView = $this->reportViewManager->find($reportViewId);

                if (!$reportView instanceof ReportViewInterface) {
                    continue;
                }

                $reportViews[] = $reportView;
            }

            if (count($reportViews) < 1) {
                $io->warning('No report views found. Please recheck your input');
            }

            return $reportViews;
        }

        $io->warning('No report views found. Please recheck your input');

        return [];
    }
}