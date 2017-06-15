<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportService;

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

    public function __construct(Logger $logger, DataSourceManagerInterface $dataSourceManager, DataSourceEntryManagerInterface $dataSourceEntryManager, ImportService $importService, $uploadFileDir, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
        $this->em = $em;
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
        // TODO: do not hardcode, use const instead
        $dataSourceEntryId = (int)$params->getRequiredParam(self::PARAM_KEY_ENTRY_ID);

        try {
            $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
            /**@var DataSourceEntryInterface $dataSourceEntry */
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                throw new Exception(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            }

            $dataSource = $dataSourceEntry->getDataSource();

            // update total rows
            $dataSourceFile = $this->importService->getDataSourceFile($dataSource->getFormat(), $dataSourceEntry->getPath());

            $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($dataSourceEntry->getFileExtension());
            if ($dataSourceTypeExtension === $dataSource->getFormat()) {
                $totalRow = $dataSourceFile->getTotalRows();
                $dataSourceEntry->setTotalRow($totalRow);

                $this->dataSourceEntryManager->save($dataSourceEntry);
            } else {
                $this->logger->error(sprintf('Data Source Entry format %s and Data Source format %s not match => skip update total rows', $dataSourceEntry->getFileExtension(), $dataSource->getFormat()));
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('could not update detected fields when entry insert, error occur: %s', $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }
}