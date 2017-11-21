<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\ReportViewDataSetManagerInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;

class UpdateDataSetIndexesByFilter  implements JobInterface
{
    const JOB_NAME = 'update_data_set_indexes_by_filter';
    const REPORT_VIEW_DATA_SET_ID = 'reportViewDataSetId';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var ReportViewDataSetManagerInterface  */
    private $reportViewDataSetManager;

    /** @var DataSetTableUtilInterface  */
    private $dataSetTableUtil;

    public function __construct(LoggerInterface $logger, ReportViewDataSetManagerInterface $reportViewDataSetManager, DataSetTableUtilInterface $dataSetTableUtil)
    {
        $this->logger = $logger;
        $this->reportViewDataSetManager = $reportViewDataSetManager;
        $this->dataSetTableUtil = $dataSetTableUtil;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        /** Validate input */
        $reportViewDataSetId = $params->getRequiredParam(self::REPORT_VIEW_DATA_SET_ID);
        $reportViewDataSet = $this->reportViewDataSetManager->find($reportViewDataSetId);

        if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
            $this->logger->error(sprintf('Report View Data Set %d not found or you do not have permission', $reportViewDataSetId));
            return;
        }

        $this->dataSetTableUtil->updateIndexesByFilter($reportViewDataSet);
    }
}