<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\ReportView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;
use UR\Service\Report\ShareableLinkUpdaterInterface;

class ReportViewChangeForSharedKeysConfigListener
{
	/** @var  array */
	protected $updateReportViewIds = [];

	/** @var ShareableLinkUpdaterInterface  */
	protected $shareableLinkUpdater;

	/**
	 * ReportViewChangeForSharedKeysConfigListener constructor.
	 * @param ShareableLinkUpdaterInterface $shareableLinkUpdater
	 */
	public function __construct(ShareableLinkUpdaterInterface $shareableLinkUpdater)
	{
		$this->shareableLinkUpdater = $shareableLinkUpdater;
	}

	/**
	 * @param LifecycleEventArgs $args
	 */
	public function postPersist(LifecycleEventArgs $args)
	{
		$entity = $args->getEntity();

		if ($entity instanceof ReportViewDataSetInterface && $entity->getReportView() !== null) {
			$this->updateReportViewIds[] = $entity->getReportView()->getId();
			return;
		}

		if ($entity instanceof ReportViewMultiViewInterface && $entity->getReportView() !== null) {
			$this->updateReportViewIds[] = $entity->getReportView()->getId();
			return;
		}
	}

	/**
	 * @param PreUpdateEventArgs $args
	 */
	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if ($entity instanceof ReportViewInterface) {
			if ($args->hasChangedField(ReportViewInterface::SHARED_KEYS_CONFIG)) {
				return;
			}
			$this->updateReportViewIds[] = $entity->getId();
			return;
		}

		if ($entity instanceof ReportViewDataSetInterface) {
			$this->updateReportViewIds[] = $entity->getReportView()->getId();
			return;
		}

		if ($entity instanceof ReportViewMultiViewInterface) {
			$this->updateReportViewIds[] = $entity->getReportView()->getId();
			return;
		}
	}

	/**
	 * @param PostFlushEventArgs $event
	 */
	public function postFlush(PostFlushEventArgs $event)
	{
		$em = $event->getEntityManager();

		/** @var ReportViewRepositoryInterface $reportViewRepository */
		$reportViewRepository = $em->getRepository(ReportView::class);

		$reportViewIds = array_values($this->updateReportViewIds);
		$this->updateReportViewIds = [];
		$update = false;

		foreach ($reportViewIds as $reportViewId) {
			$reportView = $reportViewRepository->find($reportViewId);

			if (!$reportView instanceof ReportViewInterface) {
				continue;
			}

			$this->shareableLinkUpdater->updateShareableLinks($reportView);

			$em->persist($reportView);

			$update = true;
		}

		if ($update) {
			$em->flush();
		}
	}
}