<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\ReportViewMultiView;
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
			$transformations = $multipleReportView->getTransforms();

			$subViews = $reportViewMultipleViewRepository->getByReportView($multipleReportView);
			/**@var ReportViewMultiViewInterface $subView */
			foreach ($subViews as $subView) {
				if ($subView->getSubView()->getId() === $reportView->getId()) {
					continue;
				}

				$allFields = array_merge($allFields, $subView->getSubView()->getMetrics(), $subView->getSubView()->getDimensions());
			}

			foreach ($transformations as $key => $transformation) {
				$validTransform = $this->checkIfFieldsIsMissing($transformation, $allFields);
				if ($validTransform === null) {
					unset($transformations[$key]);
				} else {
					$transformations[$key] = $validTransform;
				}
			}

			$multipleReportView->setTransforms(array_values($transformations));
			$this->updateMultipleReportViews[] = $multipleReportView;
		}
	}

	protected function removeItemsFromArray(array $originalItems, array $removingItems)
	{
		foreach($originalItems as $i=>$item) {
			if (in_array($item, $removingItems)) {
				unset($originalItems[$i]);
			}
		}

		return $originalItems;
	}


	protected function checkIfFieldsIsMissing(array $transform, array $allFields)
	{
		if (!array_key_exists(self::TYPE_KEY, $transform)) {
			return $transform;
		}

		$type = $transform[self::TYPE_KEY];
		switch($type) {
			case TransformInterface::GROUP_TRANSFORM;
				$groupFields = $transform[GroupByTransform::FIELDS_KEY];
				$fieldDiff = array_diff($groupFields, $allFields);
				$groupFields = $this->removeItemsFromArray($groupFields, $fieldDiff);
				if (empty($groupFields)) {
					return null;
				}

				$transform[GroupByTransform::FIELDS_KEY] = $groupFields;
				return $transform;
			case TransformInterface::SORT_TRANSFORM;
				$ascFields = $transform[TransformInterface::FIELDS_TRANSFORM][0][SortByTransform::FIELDS_KEY];
				$dscFields = $transform[TransformInterface::FIELDS_TRANSFORM][1][SortByTransform::FIELDS_KEY];

				$ascDiff = array_diff($ascFields, $allFields);
				$dscDiff = array_diff($dscFields, $allFields);
				if (!empty($ascDiff)) {
					$ascFields = $this->removeItemsFromArray($ascFields, $ascDiff);
				}

				if (!empty($dscDiff)) {
					$dscFields = $this->removeItemsFromArray($dscFields, $dscDiff);
				}

				if (empty($ascFields) && empty($dscFields)) {
					return null;
				}

				$transform[TransformInterface::FIELDS_TRANSFORM][0][SortByTransform::FIELDS_KEY] = $ascFields;
				$transform[TransformInterface::FIELDS_TRANSFORM][1][SortByTransform::FIELDS_KEY] = $dscFields;
				return $transform;
			case TransformInterface::COMPARISON_PERCENT_TRANSFORM;
				$fields = $transform[TransformInterface::FIELDS_TRANSFORM];
				foreach($fields as $i=>$field) {
					if (!in_array($field[ComparisonPercentTransform::NUMERATOR_KEY], $allFields) || !in_array($field[ComparisonPercentTransform::DENOMINATOR_KEY], $allFields)) {
						unset($fields[$i]);
					}
				}
				if (empty($fields)) {
					return null;
				}

				$transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
				return $transform;
			case TransformInterface::REPLACE_TEXT_TRANSFORM;
				$fields = $transform[TransformInterface::FIELDS_TRANSFORM];
				foreach($fields as $i=>$field) {
					if (!in_array($field[ReplaceTextTransform::FIELD_KEY], $allFields)) {
						unset($fields[$i]);
					}
				}

				if (empty($fields)) {
					return null;
				}

				$transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
				return $transform;
			default:
				return $transform;
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