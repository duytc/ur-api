<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\ReportView;
use UR\Repository\Core\ReportViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class AutoSetDefaultNameForReportViewListener
{

    /**
     * UpdateMetricsAndDimensionsForMultipleViewReport constructor.
     * @param ParamsBuilderInterface $paramsBuilder
     */
    public function __construct(ParamsBuilderInterface $paramsBuilder)
    {
        $this->paramsBuilder = $paramsBuilder;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof ReportView) {
            return;
        }

        $name = $entity->getName();
        if (!empty($name)) {
            return;
        }

        /** @var ReportViewRepositoryInterface $reportViewRepository */
        $reportViewRepository = $em->getRepository(ReportView::class);
        $result = $reportViewRepository->getReportViewHasMaximumId();
        if (empty($result) || !array_key_exists('idMax',$result)) {
            return;
        }

        $defaultName = $result['idMax'] +1;
        $entity->setName($defaultName);
        if (empty($entity->getAlias())){
            $entity->setAlias($defaultName);
        }

    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof ReportView) {
            return;
        }

        $name = $entity->getName();
        if (!empty($name)) {
            return;
        }

        /** @var ReportViewRepositoryInterface $reportViewRepository */
        $defaultName = $entity->getId();

        $entity->setName($defaultName);
        if (empty($entity->getAlias())){
            $entity->setAlias($defaultName);
        }
    }
}