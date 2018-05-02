<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\RedLock;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Worker\Manager;

class CountChunkRow implements JobInterface
{
    const CHUNK = 'path_chunk_file';
    const JOB_NAME = 'count_total_chunk_file';
    const ENTRY_ID = 'parse_chunk_file';
    const TOTAL = 'entry_%s_total';
    const TOTAL_CHUNKS = 'entry_%s_total_chunks';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Manager */
    private $manager;

    /** @var RedLock */
    private $redLock;

    /** @var Redis */
    private $redis;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var  DataSourceFileFactory */
    private $dataSourceFileFactory;

    private $uploadFileDirectory;

    public function __construct(LoggerInterface $logger, Manager $manager, Redis $redis, DataSourceEntryManagerInterface $dataSourceEntryManager, DataSourceFileFactory $dataSourceFileFactory, $uploadFileDirectory)
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->redis = $redis;
        $this->redLock = new RedLock([$redis]);
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->uploadFileDirectory = $uploadFileDirectory;

    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $chunk = $params->getRequiredParam(self::CHUNK);
        $dataSourceEntryId = $params->getRequiredParam(self::ENTRY_ID);

        /** Validate input */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            return;
        }

        /** Count */
        try {
            $file = $this->dataSourceFileFactory->getFile('csv', $chunk);
            $count = count($file->getRows());
            $keyTotal = sprintf(self::TOTAL, $dataSourceEntryId);
            $this->redis->incrBy($keyTotal, $count);
        } catch (\Exception $exception) {
            $this->finishJob($dataSourceEntry);

            throw $exception;
        }

        $this->finishJob($dataSourceEntry);
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function finishJob(DataSourceEntryInterface $dataSourceEntry)
    {
        try {
            $dataSourceEntryId = $dataSourceEntry->getId();

            /** Decrease job counter */
            $keyTotalChunks = sprintf(self::TOTAL_CHUNKS, $dataSourceEntryId);
            if ($this->redis->decr($keyTotalChunks) <= 0) {
                /** All chunks counted */
                $this->logger->debug(sprintf('Update total row for DataSourceEntry %d', $dataSourceEntryId));
                $keyTotal = sprintf(self::TOTAL, $dataSourceEntryId);
                $dataSourceEntry->setTotalRow($this->redis->get($keyTotal));
                //Trigger many listeners related to DataSourceEntryInterface
                $this->dataSourceEntryManager->save($dataSourceEntry);
            }
        } catch (\Exception $e) {

        }
    }
}