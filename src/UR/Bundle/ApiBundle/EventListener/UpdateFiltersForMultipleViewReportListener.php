<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class UpdateFiltersForMultipleViewReportListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_KEY = 'field';

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
			$filters = $multipleReportView->getFilters();

			foreach ($filters as $key => $filter) {
				/**@var FilterInterface $filter */
				if (!in_array($filter[self::FIELD_KEY], $allFields)) {
					unset($filters[$key]);
				}
			}
			$multipleReportView->setFilters(array_values($filters));
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