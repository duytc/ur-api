<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ReportView;
use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\ReportViewUpdaterInterface;

class RefreshAddConditionalTransformValueWhenAlterDataSetSubJob implements SubJobInterface
{
    const JOB_NAME = 'refreshAddConditionalTransformValueWhenAlterDataSetSubJob';
    const DATA_SET_ID = 'data_set_id';

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

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $reportViewAddConditionalTransformValueRepository = $this->em->getRepository(ReportViewAddConditionalTransformValue::class);
        $reportViewAddConditionalTransformValues = $reportViewAddConditionalTransformValueRepository->findAll();
        $deleted = false;

        /** @var ReportViewAddConditionalTransformValueInterface $reportViewAddConditionalTransformValue */
        foreach($reportViewAddConditionalTransformValues as $reportViewAddConditionalTransformValue) {
            try {

                if (!$reportViewAddConditionalTransformValue instanceof ReportViewAddConditionalTransformValueInterface) {
                    continue;
                }
                //if $shareConditionals is empty -> delete ReportViewAddConditionalTransformValue
                $sharedConditionals = $reportViewAddConditionalTransformValue->getSharedConditions();
                if (empty($sharedConditionals)) {
                    $this->em->remove($reportViewAddConditionalTransformValue);
                    $deleted = true;
                }
            } catch (\Exception $e) {

            }
        }

        if ($deleted) {
            $this->em->flush();
        }

        return true;
    }
}