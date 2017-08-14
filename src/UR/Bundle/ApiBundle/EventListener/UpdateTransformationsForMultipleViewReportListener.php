<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Bundle\ApiBundle\Behaviors\UpdateReportViewTrait;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class UpdateTransformationsForMultipleViewReportListener
{
	use CalculateMetricsAndDimensionsTrait;
	use UpdateReportViewTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_KEY = 'fields';
	const TYPE_KEY = 'type';

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

		if ($entity instanceof  ReportViewInterface && ($args->hasChangedField('dimensions') || $args->hasChangedField('metrics') || $args->hasChangedField('transforms'))) {
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

			if (!$multipleReportView instanceof ReportViewInterface) {
				continue;
			}

			$transformations = $multipleReportView->getTransforms();
			$formats = $multipleReportView->getFormats();

			$subViews = $reportViewMultipleViewRepository->getByReportView($multipleReportView);
			/**@var ReportViewMultiViewInterface $subView */
			foreach ($subViews as $subView) {
				if ($subView->getSubView()->getId() === $reportView->getId()) {
					continue;
				}

				$allFields = array_merge($allFields, $subView->getSubView()->getMetrics(), $subView->getSubView()->getDimensions());
			}

			foreach ($transformations as $key => $transformation) {
				$validTransform = $this->refreshTransform($transformation, $allFields);
				if ($validTransform === null) {
					unset($transformations[$key]);
				} else {
					$transformations[$key] = $validTransform;
				}
			}

			foreach ($formats as $key => $format) {
				$validFormat = $this->refreshFormat($format, $allFields);
				if ($validFormat === null) {
					unset($formats[$key]);
				} else {
					$formats[$key] = $validFormat;
				}
			}

			$multipleReportView->setTransforms(array_values($transformations));
			$multipleReportView->setFormats(array_values($formats));

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