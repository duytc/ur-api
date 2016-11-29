<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\ReportView;
use UR\Model\Core\ReportViewInterface;

class UpdateSharedKeyForReportViewListener
{
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewInterface) {
            $sharedKey = ReportView::generateSharedKey();
            $entity->setSharedKey($sharedKey);
        }
    }
}