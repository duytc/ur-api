<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;
use UR\Worker\Manager;

class EnableLargeReportViewCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    const COMMAND_NAME = 'ur:report-view:enable-large-report';
    const ALL_PUBLISHERS = 'all-publishers';
    const PUBLISHER = 'enables-user';
    const REPORT_VIEWS = 'report-views';

    const IS_LARGE = 'isLarge';
    const ENABLE = 'Enable';
    const DISABLE = 'Disable';

    /** @var Logger */
    private $logger;

    /** @var  ReportViewManagerInterface */
    private $reportViewManager;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  Manager */
    private $manager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(self::IS_LARGE, InputArgument::REQUIRED, '1 for large report, 0 for small report')
            ->addOption(self::ALL_PUBLISHERS, 'a', InputOption::VALUE_NONE,
                'For all users')
            ->addOption(self::PUBLISHER, 'u', InputOption::VALUE_OPTIONAL,
                'For special user')
            ->addOption(self::REPORT_VIEWS, 'r', InputOption::VALUE_OPTIONAL,
                'For special report views. Allow multiple report views, separated by comma. Example 15,17,29')
            ->setDescription('Enable large report view');
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
        $this->manager = $container->get('ur.worker.manager');
        $io = new SymfonyStyle($input, $output);

        $reportViews = $this->getReportViewsFromInput($input, $output);
        if (count($reportViews) < 1) {
            return;
        }

        $isLarge = $input->getArgument(self::IS_LARGE);
        $isLarge = empty($isLarge) ? false : true;
        $action = $isLarge ? self::ENABLE : self::DISABLE;

        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $io->section(sprintf("%s large report for report view %s, id = %s", $action, $reportView->getName(), $reportView->getId()));
            $reportView->setLargeReport($isLarge);
            $this->reportViewManager->save($reportView);

            if ($isLarge) {
                $this->manager->maintainPreCalculateTableForLargeReportView($reportView->getId());
            }
        }

        $io->success(sprintf("%s large report successfully for %s report views. Quit command", $action, count($reportViews)));
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