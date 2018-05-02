<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\Alert\ProcessAlertInterface;

class ProcessAlert implements JobInterface
{
    const JOB_NAME = 'processAlert';

    const PARAM_KEY_CODE = 'code';
    const PARAM_KEY_PUBLISHER_ID = 'publisherId';
    const PARAM_KEY_DETAILS = 'details';
    const PARAM_KEY_DATA_SOURCE_ID = 'dataSourceId';
    const PARAM_KEY_OPTIMIZATION_INTEGRATION_ID = 'optimizationIntegrationId';

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var ProcessAlertInterface
     */
    private $processAlert;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * AlertWorker constructor.
     * @param Logger $logger
     * @param ProcessAlertInterface $processAlert
     * @param EntityManagerInterface $em
     */
    public function __construct(Logger $logger, ProcessAlertInterface $processAlert, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->processAlert = $processAlert;
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
        $code = $params->getRequiredParam(self::PARAM_KEY_CODE);
        $publisherId = $params->getRequiredParam(self::PARAM_KEY_PUBLISHER_ID);
        $details = $params->getRequiredParam(self::PARAM_KEY_DETAILS);
        $dataSourceId = $params->getRequiredParam(self::PARAM_KEY_DATA_SOURCE_ID);
        $optimizationIntegrationId = $params->getRequiredParam(self::PARAM_KEY_OPTIMIZATION_INTEGRATION_ID);

        try {
            $this->processAlert->createAlert($code, $publisherId, $details, $dataSourceId, $optimizationIntegrationId);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('could not create alert, error occur: %s', $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }
}