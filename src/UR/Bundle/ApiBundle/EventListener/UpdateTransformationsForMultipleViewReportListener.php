<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class UpdateTransformationsForMultipleViewReportListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_KEY = 'fields';

	private $updateMultipleReportViews = [];
	/**
	 * @var ParamsBuilderInterface
	 */
	private $paramsBuilder;

	/**
	 * UpdateTransformationsForMultipleViewReportListener constructor.
	 * @param ParamsBuilderInterface $paramsBuilder
	 */
	public function __construct(ParamsBuilderInterface $paramsBuilder)
	{

		$this->paramsBuilder = $paramsBuilder;
	}

	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();
		$em = $args->getEntityManager();

		if ($entity instanceof ReportViewDataSetInterface) {
			$reportView = $entity->getReportView();
		} else if ($entity instanceof  ReportViewInterface) {
			$reportView =  $entity;
		} else {
			return;
		}

		$params = $this->paramsBuilder->buildFromReportView($reportView);
		$metricsAndDimensions = $this->getMetricsAndDimensionsForSingleView($params);
		$allFields =  array_merge($metricsAndDimensions[self::METRICS_KEY], $metricsAndDimensions[self::DIMENSIONS_KEY]);

		$reportViewMultipleViewRepository = $em->getRepository(ReportViewMultiView::class);
		/** @var ReportViewMultiViewRepositoryInterface $reportViewMultipleViewRepository */
		$reportViewMultipleReportViews = $reportViewMultipleViewRepository->getBySubView($reportView);
		if (empty($reportViewMultipleReportViews)) {
			return;
		}

		foreach ($reportViewMultipleReportViews as $reportViewMultipleReportView) {
			/**@var ReportViewMultiViewInterface $reportViewMultipleReportView */
			$multipleReportView = $reportViewMultipleReportView->getReportView();
			$transformations = $multipleReportView->getTransforms();

			foreach ($transformations as $key => $transformation) {
				if (!in_array($transformation[self::FIELD_KEY], $allFields)) {
					unset($transformations[$key]);
				}
			}
			$multipleReportView->setTransforms(array_values($transformations));

			$this->updateMultipleReportViews[] = $multipleReportView;
		}

	}

	/**
	 * @param PostFlushEventArgs $event
	 */
	public function postFlush(PostFlushEventArgs $event)
	{
		if (!empty($this->updateMultipleReportViews)) {
			$em = $event->getEntityManager();
			foreach ($this->updateMultipleReportViews as $updateMultipleReportView) {
				$em->persist($updateMultipleReportView);
			}

			$this->updateMultipleReportViews = [];
			$em->flush();
		}
	}

	protected function getMetricsKey()
	{
		return self::METRICS_KEY;
	}

	protected function getDimensionsKey()
	{
		return self::DIMENSIONS_KEY;
	}

}