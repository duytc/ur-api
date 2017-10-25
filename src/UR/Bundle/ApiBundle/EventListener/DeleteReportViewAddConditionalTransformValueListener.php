<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Entity\Core\ReportView;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;

class DeleteReportViewAddConditionalTransformValueListener
{
	/**
	 * @param LifecycleEventArgs $args
	 * @return mixed|void
	 */

	public function preRemove(LifecycleEventArgs $args)
	{
		/** @var ReportViewAddConditionalTransformValueInterface $reportViewAddConditionalTransformValue */
		$reportViewAddConditionalTransformValue = $args->getEntity();
		$em = $args->getEntityManager();

		if (!$reportViewAddConditionalTransformValue instanceof ReportViewAddConditionalTransformValueInterface) {
			return;
		}
		$id = $reportViewAddConditionalTransformValue->getId();

		/** @var ReportViewRepositoryInterface $reportViewRepository */
		$reportViewRepository = $em->getRepository(ReportView::class);

		return $reportViewRepository->removeAddConditionalTransformValue($id);
	}
}