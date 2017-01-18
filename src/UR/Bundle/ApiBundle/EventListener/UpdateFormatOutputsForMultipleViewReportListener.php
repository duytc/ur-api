<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Entity\Core\ReportViewMultiView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Repository\Core\ReportViewMultiViewRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;

class UpdateFormatOutputsForMultipleViewReportListener
{
	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_KEY = 'fields';

	use CalculateMetricsAndDimensionsTrait;

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
		$reportViewMultipleReportViews = $reportViewMultipleViewRepository->getBySubView($reportView);
		if (empty($reportViewMultipleReportViews)) {
			return;
		}

		foreach ($reportViewMultipleReportViews as $reportViewMultipleReportView) {
			/**@var ReportViewMultiViewInterface $reportViewMultipleReportView */
			$multipleReportView = $reportViewMultipleReportView->getReportView();
			$outputFormats = $multipleReportView->getFormats();

			/**@var FormatInterface[] $outputFormats */
			foreach ($outputFormats as $key => $outputFormat) {
				$fields = $outputFormat[self::FIELD_KEY];
				foreach ($fields as $fieldKey => $field) {
					if (!in_array($field, $allFields)) {
						unset($fields[$fieldKey]);
					}
				}
				if (empty($fields)) {
					unset($outputFormats[$key]);
				} else {
					$outputFormats[$key][self::FIELD_KEY] = ($fields);
				}
			}

			$multipleReportView->setFormats(array_values($outputFormats));

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