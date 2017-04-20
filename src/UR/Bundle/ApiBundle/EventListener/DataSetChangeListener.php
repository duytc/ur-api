<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\ReportViewDataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewDataSetRepositoryInterface;

class DataSetChangeListener
{
	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_TYPES_KEY = 'fieldTypes';

	/**
	 * @var array
	 */
	protected $changedDataSets;

	/**
	 * @param PreUpdateEventArgs $args
	 */
	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof DataSetInterface) {
			return;
		}

		if ($args->hasChangedField('dimensions') || $args->hasChangedField('metrics')) {
			$this->changedDataSets[] = $entity;
		}
	}

	/**
	 * @param PostFlushEventArgs $args
	 */
	public function postFlush(PostFlushEventArgs $args)
	{
		$em = $args->getEntityManager();
		$reportViewDataSetRepository = $em->getRepository(ReportViewDataSet::class);
		if (empty($this->changedDataSets)) {
			return;
		}

		foreach($this->changedDataSets as $dataSet) {
			if (!$dataSet instanceof DataSetInterface) {
				continue;
			}
			$reportViewDataSets = $reportViewDataSetRepository->getByDataSet($dataSet);
			/** @var ReportViewDataSetInterface $reportViewDataSet */
			foreach($reportViewDataSets as $reportViewDataSet) {
				$reportView = $reportViewDataSet->getReportView();
				$result = $this->refreshDimensionsMetricsForSingleReportView($reportView, $dataSet, $reportViewDataSetRepository);
				$reportView->setDimensions($result[self::DIMENSIONS_KEY]);
				$reportView->setMetrics($result[self::METRICS_KEY]);
				$reportView->setFieldTypes($result[self::FIELD_TYPES_KEY]);
				$em->persist($reportView);
			}
		}

		$this->changedDataSets = [];
		$em->flush();
	}

	/**
	 * @param ReportViewInterface $reportView
	 * @param DataSetInterface $changedDataSet
	 * @param ReportViewDataSetRepositoryInterface $reportViewDataSetRepository
	 * @return array
	 */
	protected function refreshDimensionsMetricsForSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet, ReportViewDataSetRepositoryInterface $reportViewDataSetRepository)
	{
		//calculate metrics
		$metrics = $this->getMetrics($changedDataSet);
		$dimensions = $this->getDimensions($changedDataSet);
		$fieldTypes = $reportView->getFieldTypes();
		$reportViewDataSets = $reportViewDataSetRepository->getByReportView($reportView);
		/** @var ReportViewDataSetInterface $reportViewDataSet */
		foreach($reportViewDataSets as $reportViewDataSet) {
			if ($reportViewDataSet->getDataSet()->getId() === $changedDataSet->getId()) {
				$fieldTypes = $this->getFieldTypes($changedDataSet, $fieldTypes);
				continue;
			}

			$metrics = array_merge($metrics, $this->getMetrics($reportViewDataSet->getDataSet()));
			$dimensions = array_merge($dimensions, $this->getDimensions($reportViewDataSet->getDataSet()));
		}

		return array(
			self::METRICS_KEY => $metrics,
			self::DIMENSIONS_KEY => $dimensions,
			self::FIELD_TYPES_KEY => $fieldTypes
		);
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @return array
	 */
	protected function getMetrics(DataSetInterface $dataSet)
	{
		$metrics = array_keys($dataSet->getMetrics());
		return array_map(function($field) use ($dataSet) {
			return sprintf('%s_%d', $field, $dataSet->getId());
		}, $metrics);
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @return array
	 */
	protected function getDimensions(DataSetInterface $dataSet)
	{
		$dimensions = array_keys($dataSet->getDimensions());
		return array_map(function($field) use ($dataSet) {
			return sprintf('%s_%d', $field, $dataSet->getId());
		}, $dimensions);
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @param array $fieldTypes
	 * @return array
	 */
	protected function getFieldTypes(DataSetInterface $dataSet, array $fieldTypes)
	{
		foreach($dataSet->getMetrics() as $metric=>$type) {
			$metric = sprintf('%s_%d', $metric, $dataSet->getId());
			if (!array_key_exists($metric, $fieldTypes)) {
				$fieldTypes[$metric] = $type;
			}
		}

		foreach($dataSet->getDimensions() as $dimension=>$type) {
			$dimension = sprintf('%s_%d', $dimension, $dataSet->getId());
			if (!array_key_exists($dimension, $fieldTypes)) {
				$fieldTypes[$dimension] = $type;
			}
		}

		return $fieldTypes;
	}
}