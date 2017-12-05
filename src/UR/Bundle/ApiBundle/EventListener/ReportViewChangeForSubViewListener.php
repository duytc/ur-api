<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\ReportView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;

class ReportViewChangeForSubViewListener
{
    /** @var array */
    protected $reportViewIds = [];

    /** @var  EntityManagerInterface */
    protected $em;
    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewInterface) {
            $this->reportViewIds[] = $entity->getId();
        }

        if ($entity instanceof ReportViewDataSetInterface && $entity->getReportView() instanceof ReportViewInterface) {
            $this->reportViewIds[] = $entity->getReportView()->getId();
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewDataSetInterface && $entity->getReportView() instanceof ReportViewInterface) {
            $this->reportViewIds[] = $entity->getReportView()->getId();
        }
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        $reportViewIds = $this->reportViewIds;
        $this->reportViewIds = [];

        $em = $event->getEntityManager();
        $this->em = $em;

        /** @var ReportViewRepositoryInterface $reportViewRepository */
        $reportViewRepository = $em->getRepository(ReportView::class);
        $needFlush = false;
        $reportViewIds = array_unique($reportViewIds);

        foreach ($reportViewIds as $reportViewId) {
            $masterReportView = $reportViewRepository->find($reportViewId);

            if (!$masterReportView instanceof ReportViewInterface) {
                continue;
            }

            $subViews = $reportViewRepository->getSubViewsByReportView($masterReportView);

            foreach ($subViews as $subView) {
                if (!$subView instanceof ReportViewInterface) {
                    continue;
                }

                $subView = $this->inheritReportViewDataSetsFromMasterView($masterReportView, $subView);

                /** Inherit from master view. Do not inherit filters */
                $subView->setJoinBy($masterReportView->getJoinBy());
                $subView->setTransforms($masterReportView->getTransforms());
                $subView->setShowInTotal($masterReportView->getShowInTotal());
                $subView->setFormats($masterReportView->getFormats());
                $subView->setIsShowDataSetName($masterReportView->getIsShowDataSetName());
                $subView->setWeightedCalculations($masterReportView->getWeightedCalculations());

                $em->persist($subView);

                $needFlush = true;
            }
        }

        if ($needFlush) {
            $em->flush();
        }
    }

    /**
     * @param ReportViewInterface $masterReportView
     * @param ReportViewInterface $subView
     * @return ReportViewInterface
     */
    private function inheritReportViewDataSetsFromMasterView(ReportViewInterface $masterReportView, ReportViewInterface $subView)
    {
        $masterReportViewDataSets = $masterReportView->getReportViewDataSets();
        if ($masterReportViewDataSets instanceof Collection) {
            $masterReportViewDataSets = $masterReportViewDataSets->toArray();
        }

        $this->deleteOldReportViewDataSets($subView);

        $subViewReportViewDataSets = [];
        foreach ($masterReportViewDataSets as $reportViewDataSet) {
            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $subViewReportViewDataSet = clone $reportViewDataSet;
            $subViewReportViewDataSet->setId(null);
            $subViewReportViewDataSet->setReportView($subView);

            $subViewReportViewDataSets[] = $subViewReportViewDataSet;
        }

        $subView->setReportViewDataSets($subViewReportViewDataSets);

        return $subView;
    }

    /**
     * @param ReportViewInterface $subView
     */
    private function deleteOldReportViewDataSets(ReportViewInterface $subView)
    {
        $reportViewDataSets = $subView->getReportViewDataSets();
        if ($reportViewDataSets instanceof Collection) {
            $reportViewDataSets = $reportViewDataSets->toArray();
        }

        foreach ($reportViewDataSets as $reportViewDataSet) {
            $this->em->remove($reportViewDataSet);
        }

        $this->em->flush();
    }
}