<?php

namespace UR\Bundle\ApiBundle\EventListener;


use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\ReportViewInterface;


/**
 * Class UpdateLastActivityForDataSet
 * update last Activity for report view
 */
class UpdateLastActivityForReportViewListener
{
    /**
     * @var array|ReportViewInterface[]
     */
    protected $dataSetToBeUpdatedList = [];

    public function prePersist(LifecycleEventArgs $args)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $args->getEntity();
        if (!$reportView instanceof ReportViewInterface) {
            return;
        }

        $reportView->setLastActivity(new DateTime());
    }

    public function preUpdate(LifecycleEventArgs $args )
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $args->getEntity();

        if (!$reportView instanceof ReportViewInterface) {
            return;
        }

        $reportView->setLastActivity(new DateTime());
    }
}