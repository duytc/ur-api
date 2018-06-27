<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Pubvantage\RedLock;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportService;
use UR\Worker\Manager;

class UpdateTotalRowWhenEntryInserted implements JobInterface
{
    const JOB_NAME = 'updateTotalRowWhenEntryInserted';

    const PARAM_KEY_ENTRY_ID = 'entryId';

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /** @var ImportService */
    private $importService;

    /** @var string */
    private $uploadFileDir;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /** @var Manager */
    private $workerManager;

    private $redis;

    private $redLock;

    public function __construct(
        Logger $logger,
        DataSourceManagerInterface $dataSourceManager,
        DataSourceEntryManagerInterface $dataSourceEntryManager,
        ImportService $importService,
        $uploadFileDir,
        EntityManagerInterface $em,
        Manager $workerManager,
        Redis $redis)
    {
        $this->logger = $logger;
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
        $this->em = $em;
        $this->workerManager = $workerManager;
        $this->redis = $redis;
        $this->redLock = new RedLock([$redis]);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $dataSourceEntryId = (int)$params->getRequiredParam(self::PARAM_KEY_ENTRY_ID);

        try {
            $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                throw new Exception(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            }

            // update total rows
            if (!empty($dataSourceEntry->getChunks()) && is_array($dataSourceEntry->getChunks())) {
                $this->logger->notice('update total row with chunk files');
                //Set total chunks for all job as share memory
                $keyTotal = sprintf(CountChunkRow::TOTAL, $dataSourceEntryId);
                $keyTotalChunks = sprintf(CountChunkRow::TOTAL_CHUNKS, $dataSourceEntryId);

                $this->redis->set($keyTotalChunks, count($dataSourceEntry->getChunks()));
                $this->redis->set($keyTotal, 0);

                //Create jobs
                foreach ($dataSourceEntry->getChunks() as $chunk) {
                    $this->workerManager->createJobCountChunkRow($chunk, $dataSourceEntryId);
                }

                return;
            }

            $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($dataSourceEntry->getFileExtension());
            $dataSource = $dataSourceEntry->getDataSource();
            $dataSourceFile = $this->importService->getDataSourceFile($dataSourceTypeExtension, $dataSourceEntry->getPath(), $dataSource->getSheets());

            $totalRow = $dataSourceFile->getTotalRows($dataSource->getSheets());
            $dataSourceEntry->setTotalRow($totalRow);

            $this->dataSourceEntryManager->save($dataSourceEntry);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('could not update detected fields when entry insert, error occur: %s', $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }
}