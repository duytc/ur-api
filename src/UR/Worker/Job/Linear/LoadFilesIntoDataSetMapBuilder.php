<?php

namespace UR\Worker\Job\Linear;

use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
use UR\Worker\Manager;

class LoadFilesIntoDataSetMapBuilder implements SplittableJobInterface
{
    const JOB_NAME = 'loadFilesIntoDataSetMapBuilder';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    /** @var Manager */
    private $manager;

    /** @var DataSetManagerInterface */
    private $dataSetManager;

    /** @var JobCounterInterface */
    private $jobCounter;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository, Manager $manager, DataSetManagerInterface $dataSetManager, JobCounterInterface $jobCounter)
    {
        $this->scheduler = $scheduler;

        $this->logger = $logger;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
        $this->manager = $manager;
        $this->dataSetManager = $dataSetManager;
        $this->jobCounter = $jobCounter;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        // do create all linear jobs for data set
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);

        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return false;
        }

        $linearTubeName = DataSetLoadFilesConcurrentJobScheduler::getDataSetTubeName($dataSetId);
        $mapBuilderConfigs = $dataSet->getMapBuilderConfigs();
        $mapBuilderConfigs = $mapBuilderConfigs instanceof Collection ? $mapBuilderConfigs->toArray() : $mapBuilderConfigs;

        if (count($mapBuilderConfigs) < 1) {
            return false;
        }

        foreach ($mapBuilderConfigs as $config) {
            if (!$config instanceof MapBuilderConfigInterface) {
                continue;
            }

            $this->jobCounter->increasePendingJob($linearTubeName);
            $this->manager->loadDataSetMapBuilder($dataSetId, $config->getId());
        }
    }
}