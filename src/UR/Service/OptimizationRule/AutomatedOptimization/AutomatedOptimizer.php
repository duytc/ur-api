<?php

namespace UR\Service\OptimizationRule\AutomatedOptimization;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Redis;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\Alert\ActionRequire\ActionRequireFactoryInterface;
use UR\Service\PublicSimpleException;
use UR\Worker\Manager;

class AutomatedOptimizer implements AutomatedOptimizerInterface
{
    use OptimizationRuleUtilTrait;

    const DEFAULT_TIME_OUT = 6;

    /** @var  LoggerInterface */
    private $logger;

    /** @var Manager */
    private $manager;

    /** @var array */
    private $optimizers;

    /** @var ActionRequireFactoryInterface */
    private $actionRequireFactory;

    /** @var OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    /** @var Redis */
    private $redis;

    /**
     * AutomatedOptimizer constructor.
     * @param LoggerInterface $logger
     * @param Manager $manager
     * @param ActionRequireFactoryInterface $actionRequireFactory
     * @param OptimizationIntegrationManagerInterface $optimizationIntegrationManager
     * @param Redis $redis
     * @param $optimizers
     */
    public function __construct(LoggerInterface $logger, Manager $manager, ActionRequireFactoryInterface $actionRequireFactory,
                                OptimizationIntegrationManagerInterface $optimizationIntegrationManager, Redis $redis, $optimizers)
    {
        $this->optimizers = $optimizers;
        $this->logger = $logger;
        $this->actionRequireFactory = $actionRequireFactory;
        $this->manager = $manager;
        $this->optimizationIntegrationManager = $optimizationIntegrationManager;
        $this->redis = $redis;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizer(OptimizationIntegrationInterface $optimizationIntegration)
    {
        foreach ($this->optimizers as $optimizer) {
            if (!$optimizer instanceof OptimizerInterface) {
                continue;
            }

            if ($optimizer->supportOptimizationIntegration($optimizationIntegration)) {
                return $optimizer;
            }
        }

        if (empty($optimizationIntegration->getPlatformIntegration())) {
            throw new PublicSimpleException(sprintf('Integration %s (ID: %s) not belong to any platform', $optimizationIntegration->getName(), $optimizationIntegration->getId()));
        }

        throw new PublicSimpleException(sprintf('Can not find optimizer for platform: %s', $optimizationIntegration->getPlatformIntegration()));
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param array $optimizationIntegrationIds
     * @return bool|mixed
     * @throws \Exception
     */
    public function optimizeForRule(OptimizationRuleInterface $optimizationRule, $optimizationIntegrationIds = null)
    {
        $optimizationIntegrations = $optimizationRule->getOptimizationIntegrations();
        if ($optimizationIntegrations instanceof Collection) {
            $optimizationIntegrations = $optimizationIntegrations->toArray();
        }

        if (empty($optimizationIntegrations)) {
            return true;
        }

        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }

            if (is_array($optimizationIntegrationIds)) {
                if (!in_array($optimizationIntegration->getId(), $optimizationIntegrationIds)) {
                    continue;
                }
            }

            if (!$this->isOutOfDate($optimizationIntegration)) {
                continue;
            }

            $optimizer = $this->getOptimizer($optimizationIntegration);

            $lockKey = sprintf("optimization_integration_%s_lock", $optimizationIntegration->getId());
            if ($this->redis->exists($lockKey)) {
                continue;
            }

            //Lock
            $this->redis->set($lockKey, 1000, self::DEFAULT_TIME_OUT);
            if (!$optimizationIntegration->isUserConfirm()) {
                try {
                    if ($optimizationIntegration->isRequirePendingAlert() && $optimizationRule->getPublisher() instanceof PublisherInterface) {
                        $this->actionRequireFactory->createActionRequireAlert($optimizationIntegration, $optimizer->testForOptimizationIntegration($optimizationIntegration));
                        $this->logger->info(sprintf('Create action require alert successfully for optimization integration %d', $optimizationIntegration->getId()));
                        continue;
                    }
                } catch (\Exception $exception) {
                    $newMessage = sprintf($exception->getMessage(). ' - integration id: %d -', $optimizationIntegration->getId());

                    throw new \Exception($newMessage, $exception->getCode(), $exception);
                }
            }

            try {
                // set startRescoreAt after finished update cache
                $startRescoreAt = new DateTime('now');
                $optimizationIntegration->setStartRescoreAt($startRescoreAt);

                $optimizeResult = $optimizer->optimizeForOptimizationIntegration($optimizationIntegration);
                $optimizeResultDetail = (is_array($optimizeResult) && array_key_exists('message', $optimizeResult))
                    ? $optimizeResult['message']
                    : '';

                // set endRescoreAt after finished update cache
                $endRescoreAt = new DateTime('now');
                $optimizationIntegration->setEndRescoreAt($endRescoreAt);
                $this->optimizationIntegrationManager->save($optimizationIntegration);

                if ($optimizationIntegration->isRequireSuccessAlert() && $optimizationRule->getPublisher() instanceof PublisherInterface) {
                    $this->manager->processAlert(
                        AlertInterface::ALERT_CODE_OPTIMIZATION_INTEGRATION_REFRESH_CACHE_SUCCESS,
                        $optimizationRule->getPublisher()->getId(),
                        [
                            "message" => sprintf("Update 3rd party integration successfully for optimization rule '%s' (ID = '%s'), config %s (ID = %s)", $optimizationRule->getName(), $optimizationRule->getId(), $optimizationIntegration->getName(), $optimizationIntegration->getId())
                        ],
                        $dataSourceId = null,
                        $optimizationIntegration->getId()
                    );
                }

                $this->logger->info(sprintf('Update 3rd party integrations successfully for optimization integration %d. Detail: %s', $optimizationIntegration->getId(), $optimizeResultDetail));
            } catch (\Exception $exception) {
                $newMessage = sprintf($exception->getMessage(). ' - integration id: %d -', $optimizationIntegration->getId());

                throw new \Exception($newMessage, $exception->getCode(), $exception);
            }
        }

        return true;
    }
}