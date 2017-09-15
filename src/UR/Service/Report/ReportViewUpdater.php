<?php
namespace UR\Service\Report;

use Doctrine\ORM\EntityManagerInterface;
use UR\Bundle\ApiBundle\Behaviors\UpdateReportViewTrait;
use UR\Entity\Core\ReportViewDataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewDataSetRepositoryInterface;

class ReportViewUpdater implements ReportViewUpdaterInterface
{
	use UpdateReportViewTrait;

	/**
	 * @var EntityManagerInterface
	 */
	private $em;

	/**
	 * @var ReportViewDataSetRepositoryInterface
	 */
	private $reportViewDataSetRepository;

	/**
	 * ReportViewUpdater constructor.
	 * @param EntityManagerInterface $em
	 */
	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
		$this->reportViewDataSetRepository = $this->em->getRepository(ReportViewDataSet::class);
	}


	/**
	 * @inheritdoc
	 */
	public function refreshSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet)
	{
		//calculate metrics
		$metrics = $this->getMetrics($changedDataSet);
		$updatedMetrics = $this->removeDimensionMetricSuffix($metrics);
		$dimensions = $this->getDimensions($changedDataSet);
		$updatedDimensions = $this->removeDimensionMetricSuffix($dimensions);
		$fieldTypes = $reportView->getFieldTypes();
		$reportViewDataSets = $this->reportViewDataSetRepository->getByReportView($reportView);

		/** @var ReportViewDataSetInterface $reportViewDataSet */
		foreach ($reportViewDataSets as $reportViewDataSet) {
			if ($reportViewDataSet->getDataSet()->getId() === $changedDataSet->getId()) {
				$fieldTypes = $this->getFieldTypes($changedDataSet, $fieldTypes);
				$reportViewDataSet->setDimensions($updatedDimensions);
				$reportViewDataSet->setMetrics($updatedMetrics);
				$this->em->merge($reportViewDataSet);
				continue;
			}

			$metrics = array_merge($metrics, $this->getMetrics($reportViewDataSet->getDataSet()));
			$dimensions = array_merge($dimensions, $this->getDimensions($reportViewDataSet->getDataSet()));
		}

		$allDimensionsMetrics = array_merge($metrics, $dimensions);
		$transforms = $this->refreshTransformsForSingleReportView($reportView, $allDimensionsMetrics);
		$formats = $this->refreshFormatsForSingleReportView($reportView, $allDimensionsMetrics);
		$joinConfigs = $this->refreshJoinConfigsForSingleReportView($reportView, $allDimensionsMetrics);
		$showInTotal = $this->refreshShowInTotalsForSingleReportView($reportView, $allDimensionsMetrics);

		$reportView->setDimensions($metrics);
		$reportView->setMetrics($dimensions);
		$reportView->setFieldTypes($fieldTypes);
		$reportView->setTransforms($transforms);
		$reportView->setFormats($formats);
		$reportView->setJoinBy($joinConfigs);
		$reportView->setShowInTotal($showInTotal);

		$this->em->persist($reportView);
		$this->em->flush();

		return $reportView;
	}

	/**
	 * @param ReportViewInterface $reportView
	 * @param array $allDimensionsMetrics
	 * @return array
	 */
	private function refreshTransformsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
	{
		$transforms = $reportView->getTransforms();
		foreach ($transforms as $key => $transform) {
			$validTransform = $this->refreshTransform($transform, $allDimensionsMetrics);
			if ($validTransform === null) {
				unset($transforms[$key]);
			} else {
				$transforms[$key] = $validTransform;
			}
		}

		return array_values($transforms);
	}

	/**
	 * @param ReportViewInterface $reportView
	 * @param array $allDimensionsMetrics
	 * @return array
	 */
	private function refreshFormatsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
	{
		$formats = $reportView->getFormats();
		foreach ($formats as $i => $format) {
			$validFormat = $this->refreshFormat($format, $allDimensionsMetrics);
			if ($validFormat === null) {
				unset($formats[$i]);
			} else {
				$formats[$i] = $validFormat;
			}
		}

		return array_values($formats);
	}

	/**
	 * @param ReportViewInterface $reportView
	 * @param array $allDimensionsMetrics
	 * @return array
	 */
	private function refreshJoinConfigsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
	{
		$joinConfigs = $reportView->getJoinBy();
		foreach ($joinConfigs as $i => $joinConfig) {
			$joinConfigs[$i] = $this->refreshJoinConfig($joinConfig, $allDimensionsMetrics);
		}

		return $joinConfigs;
	}

	/**
	 * @param array $joinConfig
	 * @param $allDimensionsMetrics
	 * @return array
	 */
	private function refreshJoinConfig(array $joinConfig, $allDimensionsMetrics)
	{
		$joinFields = $joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
		foreach ($joinFields as $i => &$joinField) {
			$field = sprintf('%s_%d', $joinField[SqlBuilder::JOIN_CONFIG_FIELD], $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET]);
			if (!in_array($field, $allDimensionsMetrics)) {
				$joinField[SqlBuilder::JOIN_CONFIG_FIELD] = null;
			}
		}

		$joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] = $joinFields;
		return $joinConfig;
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @return array
	 */
	private function getMetrics(DataSetInterface $dataSet)
	{
		$metrics = array_keys($dataSet->getMetrics());
		return array_map(function ($field) use ($dataSet) {
			return sprintf('%s_%d', $field, $dataSet->getId());
		}, $metrics);
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @return array
	 */
	private function getDimensions(DataSetInterface $dataSet)
	{
		$dimensions = array_keys($dataSet->getDimensions());
		return array_map(function ($field) use ($dataSet) {
			return sprintf('%s_%d', $field, $dataSet->getId());
		}, $dimensions);
	}

	/**
	 * @param DataSetInterface $dataSet
	 * @param array $fieldTypes
	 * @return array
	 */
	private function getFieldTypes(DataSetInterface $dataSet, array $fieldTypes)
	{
		foreach ($dataSet->getMetrics() as $metric => $type) {
			$metric = sprintf('%s_%d', $metric, $dataSet->getId());
			if (!array_key_exists($metric, $fieldTypes)) {
				$fieldTypes[$metric] = $type;
			}
		}

		foreach ($dataSet->getDimensions() as $dimension => $type) {
			$dimension = sprintf('%s_%d', $dimension, $dataSet->getId());
			if (!array_key_exists($dimension, $fieldTypes)) {
				$fieldTypes[$dimension] = $type;
			}
		}

		return $fieldTypes;
	}

	/**
	 * @param array $dimensionOrMetrics
	 * @return array
	 */
	private function removeDimensionMetricSuffix(array $dimensionOrMetrics)
	{
		return array_map(function ($dimensionOrMetric) {
			return preg_replace('/^(.*)_(\d+)$/', '$1', $dimensionOrMetric);
		}, $dimensionOrMetrics);
	}

	/**
	 * @param ReportViewInterface $reportView
	 * @param array $allDimensionsMetrics
	 * @return array
	 */
	private function refreshShowInTotalsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
	{
		$showInTotals = $reportView->getShowInTotal();
		$missingFields = array_diff($showInTotals, $allDimensionsMetrics);

		$showInTotals = $this->removeItemsFromArray($showInTotals, $missingFields);

		return array_values($showInTotals);
	}
}