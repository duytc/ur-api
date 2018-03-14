<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ReportView;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\ReportViewUpdaterInterface;

class UpdateReportViewWhenAlterDataSetSubJob implements SubJobInterface
{
    const JOB_NAME = 'updateReportViewWhenAlterDataSetSubJob';
    const DATA_SET_ID = 'data_set_id';

    const NEW_FIELDS = AlterDataSetTableSubJob::NEW_FIELDS;
    const UPDATE_FIELDS = AlterDataSetTableSubJob::UPDATE_FIELDS;
    const DELETED_FIELDS = AlterDataSetTableSubJob::DELETED_FIELDS;

	/** @var ReportViewUpdaterInterface  */
	protected $reportViewUpdater;

	/**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em, ReportViewUpdaterInterface $reportViewUpdater)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
		$this->reportViewUpdater = $reportViewUpdater;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);

        $newFields = $params->getRequiredParam(self::NEW_FIELDS);
        $updatedFields = $params->getRequiredParam(self::UPDATE_FIELDS);
        $deletedFields = $params->getRequiredParam(self::DELETED_FIELDS);

        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $reportViewRepository = $this->em->getRepository(ReportView::class);
        $dataSet = $dataSetRepository->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            return false;
        }

        $reportViews = $reportViewRepository->getReportViewThatUseDataSet($dataSet);
        /** @var ReportViewInterface $reportView */
        foreach($reportViews as $reportView) {
			$this->reportViewUpdater->refreshSingleReportView($reportView, $dataSet, $newFields, $updatedFields, $deletedFields);
        }

        return true;
    }
}