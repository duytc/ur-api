<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
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

    public function __construct(LoggerInterface $logger, Manager $manager, ReportViewManagerInterface $reportViewManager, $largeThreshold, LargeReportMaintainerInterface $largeReportMaintainer)
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->reportViewManager = $reportViewManager;
        $this->largeThreshold = $largeThreshold;
        $this->largeReportMaintainer = $largeReportMaintainer;
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

        $reportView = $this->reportViewManager->find($reportViewId);

        if (!$reportView instanceof ReportViewInterface) {
            return;
        }

        if (!$this->isLargeReportView($reportView, $this->largeThreshold)) {
            return;
        }

        $this->largeReportMaintainer->maintainerLargeReport($reportView);
    }
}