<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Entity\Core\Alert;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Repository\Core\AlertRepositoryInterface;

class DisableOldActionRequiredAlertsListener
{
    private $newAlerts = [];

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof AlertInterface) {
            return;
        }

        $this->newAlerts[] = $entity;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (empty($this->newAlerts)) {
            return;
        }

        $newAlerts = $this->newAlerts;
        $this->newAlerts = [];
        $em = $args->getEntityManager();
        /** @var AlertRepositoryInterface $alertRepository */
        $alertRepository = $em->getRepository(Alert::class);

        $actionRequiredAlerts = array_filter($newAlerts, function ($alert) {
            return
                $alert instanceof AlertInterface &&
                !$alert->getIsRead() &&
                $alert->getType() == AlertInterface::ALERT_TYPE_ACTION_REQUIRED &&
                $alert->getOptimizationIntegration() instanceof OptimizationIntegrationInterface;
        });

        $count = 0;
        foreach ($actionRequiredAlerts as $newAlert) {
            if (!$newAlert instanceof AlertInterface) {
                continue;
            }

            $oldActionRequiredAlerts = $alertRepository->findOldActionRequiredAlert($newAlert);
            foreach ($oldActionRequiredAlerts as $oldAlert) {
                if (!$oldAlert instanceof AlertInterface) {
                    continue;
                }

                //Update type, message, data
                $oldAlert->setType(AlertInterface::ALERT_TYPE_INFO);

                $detail = $oldAlert->getDetail();
                $detail = is_array($detail) ? $detail : [$detail];
                $detail['message'] = sprintf("%s. %s", $detail['message'], "This required action is canceled dues to another recent required action occurred");
                $oldAlert->setDetail($detail);

                $em->merge($oldAlert);
                $count++;
            }
        }

        if ($count > 0) {
            $em->flush();
        }
    }
}