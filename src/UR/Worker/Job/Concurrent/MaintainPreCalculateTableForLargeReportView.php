<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\Process\Process;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\LargeReport\LargeReportMaintainerInterface;
use UR\Worker\Manager;

class MaintainPreCalculateTableForLargeReportView implements JobInterface
{
    use LargeReportViewUtilTrait;

    const JOB_NAME = 'maintain_pre_calculate_table_for_large_report_view';
    const REPORT_VIEW_ID = 'report_view_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Manager */
    private $manager;

    /** @var ReportViewManagerInterface */
    private $reportViewManager;

    /** @var  int */
    private $largeThreshold;

    /** @var LargeReportMaintainerInterface */
    private $largeReportMaintainer;

    /** @var EntityManagerInterface  */
    private $em;

    public function __construct(LoggerInterface $logger, Manager $manager, ReportViewManagerInterface $reportViewManager, $largeThreshold, LargeReportMaintainerInterface $largeReportMaintainer, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->reportViewManager = $reportViewManager;
        $this->largeThreshold = $largeThreshold;
        $this->largeReportMaintainer = $largeReportMaintainer;
        $this->em = $em;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $reportViewId = $params->getRequiredParam(self::REPORT_VIEW_ID);

        if (empty($reportViewId)) {
            return;
        }

        $reportView = null;

        try {
            $reportView = $this->reportViewManager->find($reportViewId);
        } catch (\Exception $e) {
            /**
             * When MySQL server gone away. Noway to use SQL to remove lock from report view
             * Temporary solution is re-register this job to run after restart worker
             * */
            try {
                $this->logger->warning("MySQL server has gone away. Need restart worker");
                $processes[] = new Process('sudo service mysql restart');
                $processes[] = new Process('sudo service mysqld restart');
                $success = false;

                foreach ($processes as $process) {
                    $process->run();
                    $success = $success || $process->isSuccessful();
                }

                $this->manager->maintainPreCalculateTableForLargeReportView($reportViewId);

                if (FALSE == $this->em->getConnection()->ping()) {
                    $this->em->getConnection()->close();
                    $this->em->getConnection()->connect();
                }

                sleep(20);
            } catch (\Exception $e) {

            }

            return;
        }

        if (!$reportView instanceof ReportViewInterface) {
            return;
        }

        if (!$this->isLargeReportView($reportView, $this->largeThreshold)) {
            return;
        }

        $this->largeReportMaintainer->maintainLargeReport($reportView);
    }
}