<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceFileFactory;

class SplitHugeFile  implements JobInterface
{
    const JOB_NAME = 'split_huge_file';
    const DATA_SOURCE_ENTRY_ID = 'data_source_entry_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var DataSourceFileFactory  */
    private $dataSourceFileFactory;

    private $fileSizeThreshold;

    /**
     * SplitHugeFile constructor.
     * @param LoggerInterface $logger
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param DataSourceFileFactory $dataSourceFileFactory
     */
    public function __construct(LoggerInterface $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, DataSourceFileFactory $dataSourceFileFactory, $fileSizeThreshold)
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->dataSourceFileFactory = $dataSourceFileFactory;
        $this->fileSizeThreshold = $fileSizeThreshold;
    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSourceEntryId = $params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            return;
        }

        $fileSize = filesize($this->dataSourceFileFactory->getAbsolutePath($dataSourceEntry->getPath()));

        if ($fileSize > $this->fileSizeThreshold) {
            $chunks = $this->dataSourceFileFactory->splitHugeFile($dataSourceEntry);

            if (!empty($chunks)) {
                $dataSourceEntry->setSeparable(true);
                $dataSourceEntry->setChunks($chunks);

                $this->dataSourceEntryManager->save($dataSourceEntry);
            }
        }
    }
}