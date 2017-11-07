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

class CountChunkRow  implements JobInterface
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

    /** @var Manager  */
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


        $keyTotal = sprintf(self::TOTAL, $dataSourceEntryId);
        $keyTotalChunks = sprintf(self::TOTAL_CHUNKS, $dataSourceEntryId);

        /** Count */
        $file = $this->dataSourceFileFactory->getFile('csv', $chunk);
        $count = count($file->getRows());
        $this->redis->incrBy($keyTotal, $count);

        /** Decrease number job */
        $this->redis->decr($keyTotalChunks);

        /** Merge result */
        $totalChunk = $this->redis->decr($keyTotalChunks);
        //all chunk parsed
        if ($totalChunk <= 0) {
            $countAll = $this->redis->get($keyTotal);
            $this->logger->debug(sprintf('Update total row for DataSourceEntry %d', $dataSourceEntryId));
            $dataSourceEntry->setTotalRow($countAll);
            $this->dataSourceEntryManager->save($dataSourceEntry);
        }
    }
}