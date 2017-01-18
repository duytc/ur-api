<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class UpdateMetricsAndDimensionsForMultipleViewReportListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';

	/**
	 * @var ParamsBuilderInterface
	 */
	private $paramsBuilder;

	private $updateMultipleReportViews = [];


	/**
	 * UpdateMetricsAndDimensionsForMultipleViewReport constructor.
	 * @param ParamsBuilderInterface $paramsBuilder
	 */
	public function __construct(ParamsBuilderInterface $paramsBuilder)
	{
		$this->paramsBuilder = $paramsBuilder;
	}

	/**
	 * @param PreUpdateEventArgs $args
	 */
	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();
		$em = $args->getEntityManager();

		if ($entity instanceof ReportViewDataSetInterface) {
			$reportView = $entity->getReportView();
		} else if ($entity instanceof ReportViewInterface) {
			$reportView = $entity;
		} else {
			return;
		}

		$params = $this->paramsBuilder->buildFromReportView($reportView);
		$metricsAndDimensions = $this->getMetricsAndDimensionsForSingleView($params);
		$allFields = array_merge($metricsAndDimensions[self::METRICS_KEY], $metricsAndDimensions[self::DIMENSIONS_KEY]);

		$reportViewMultipleViewRepository = $em->getRepository(ReportViewMultiView::class);
		/** @var ReportViewMultiViewRepositoryInterface $reportViewMultipleViewRepository */
		$multipleReportViews = $reportViewMultipleViewRepository->getBySubView($reportView);
		if (empty($multipleReportViews)) {
			return;
		}

		foreach ($multipleReportViews as $multipleReportView) {
			/**@var ReportViewMultiViewInterface $multipleReportView */
			$reportViewMultipleReportViewDimensions = $multipleReportView->getDimensions();
			$reportViewMultipleReportViewMetrics = $multipleReportView->getMetrics();

			foreach ($reportViewMultipleReportViewDimensions as $key => $reportViewMultipleReportViewDimension) {
				if (!in_array($reportViewMultipleReportViewDimension, $allFields)) {
					unset($reportViewMultipleReportViewDimensions[$key]);
				}
			}

			foreach ($reportViewMultipleReportViewMetrics as $key => $reportViewMultipleReportViewMetric) {
				if (!in_array($reportViewMultipleReportViewMetric, $allFields)) {
					unset($reportViewMultipleReportViewMetrics[$key]);
				}

			}
			$multipleReportView->setDimensions(array_values($reportViewMultipleReportViewDimensions));
			$multipleReportView->setMetrics(array_values($reportViewMultipleReportViewMetrics));
		}

		$this->updateMultipleReportViews = $multipleReportViews;
	}

	/**
	 * @param PostFlushEventArgs $event
	 */
	public function postFlush(PostFlushEventArgs $event)
	{
		if (!empty($this->updateMultipleReportViews)) {
			$em = $event->getEntityManager();
			foreach ($this->updateMultipleReportViews as $multipleReportView) {
				$em->persist($multipleReportView);
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