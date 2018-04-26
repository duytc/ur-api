<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use stdClass;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;

class NotifyPlatformIntegrationWhenOptimizationIntegrationChangeListener
{
    /* important: keep sync name from this to tagcade api (in tagcade api, this is function name in worker!!!) */
    const NOTIFY_PLATFORM_INTEGRATION_JOB_NAME_REMOVED_OPTIMIZATION_INTEGRATION = 'updateAdSlotCacheWhenRemoveOptimizationIntegration';

    /** @var PheanstalkProxyInterface */
    private $beanstalk;

    /** @var string */
    private $pubvantageIntegrationTubeName;

    public function __construct(PheanstalkProxyInterface $beanstalk, $pubvantageIntegrationTubeName)
    {
        $this->beanstalk = $beanstalk;
        $this->pubvantageIntegrationTubeName = $pubvantageIntegrationTubeName;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Doctrine\DBAL\DBALException
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $optimizationIntegration = $args->getEntity();
        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return;
        }

        $platformIntegration = $optimizationIntegration->getPlatformIntegration();

        switch ($platformIntegration) {
            case PubvantageOptimizer::PLATFORM_INTEGRATION: // pubvantage display api
                // send job to Pubvantage platform
                $params = new stdClass();
                $params->adSlots = $optimizationIntegration->getAdSlots();

                $payload = new stdClass();
                $payload->task = self::NOTIFY_PLATFORM_INTEGRATION_JOB_NAME_REMOVED_OPTIMIZATION_INTEGRATION;
                $payload->params = $params;

                $this->beanstalk->putInTube($this->pubvantageIntegrationTubeName, json_encode($payload));

                break;
        }
    }
}