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
	public function refreshSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet, array $newFields = [], array $updatedFields = [], array $deletedFields = [])
	{
		// get dimensions/metrics of changedDataSet
		$metrics = $this->getMetrics($changedDataSet);
		//$metricsWithoutSuffix = $this->removeDimensionMetricSuffix($metrics);

		$dimensions = $this->getDimensions($changedDataSet);
		//$dimensionsWithoutSuffix = $this->removeDimensionMetricSuffix($dimensions);
		
        $fieldTypes = $reportView->getFieldTypes();
		$reportViewDataSets = $this->reportViewDataSetRepository->getByReportView($reportView);

		/** @var ReportViewDataSetInterface $reportViewDataSet */
		foreach ($reportViewDataSets as $reportViewDataSet) {
			if ($reportViewDataSet->getDataSet()->getId() === $changedDataSet->getId()) {
                // update fieldTypes
				$fieldTypes = $this->getFieldTypes($changedDataSet, $fieldTypes);

                // update reportViewDataSet
				$this->updateReportViewDataSet($reportViewDataSet, $newFields, $updatedFields, $deletedFields);

				continue;
			}

			// merge dimensions/metrics from other data sets
			$metrics = array_merge($metrics, $this->getMetrics($reportViewDataSet->getDataSet()));
			$dimensions = array_merge($dimensions, $this->getDimensions($reportViewDataSet->getDataSet()));
		}

		// update report view
		$allDimensionsMetrics = array_merge($metrics, $dimensions);
		$transforms = $this->refreshTransformsForSingleReportView($reportView, $allDimensionsMetrics);
		$formats = $this->refreshFormatsForSingleReportView($reportView, $allDimensionsMetrics);
		$joinConfigs = $this->refreshJoinConfigsForSingleReportView($reportView, $allDimensionsMetrics, $updatedFields, $changedDataSet);
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
	 * @param $updatedFields
	 * @param DataSetInterface $changedDataSet
	 * @return array
	 */
	private function refreshJoinConfigsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics, $updatedFields, DataSetInterface $changedDataSet)
	{
		$joinConfigs = $reportView->getJoinBy();
		foreach ($joinConfigs as $i => $joinConfig) {
			$joinConfigs[$i] = $this->refreshJoinConfig($joinConfig, $allDimensionsMetrics, $updatedFields, $changedDataSet);
		}

		return $joinConfigs;
	}

	/**
	 * @param array $joinConfig
	 * @param $allDimensionsMetrics
	 * @param $updatedFields
	 * @param DataSetInterface $changedDataSet
	 * @return array
	 */
	private function refreshJoinConfig(array $joinConfig, $allDimensionsMetrics, $updatedFields, DataSetInterface $changedDataSet)
	{
		$joinFields = $joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
		$updatedFields = is_array($updatedFields) ? $updatedFields : [$updatedFields];
		
		foreach ($joinFields as $i => &$joinField) {
			if (!array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $joinField) || !array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $joinField)) {
				continue;
			}
			$field = sprintf('%s_%d', $joinField[SqlBuilder::JOIN_CONFIG_FIELD], $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET]);
			
			foreach ($updatedFields as $oldField => $newField) {
				if ($joinField[SqlBuilder::JOIN_CONFIG_DATA_SET] == $changedDataSet->getId() && $joinField[SqlBuilder::JOIN_CONFIG_FIELD] == $oldField) {
					$joinField[SqlBuilder::JOIN_CONFIG_FIELD] = $newField;
					$field = sprintf('%s_%d', $newField, $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET]);
				}
			}

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

            // add new fieldType mapping if not existed
			if (!array_key_exists($metric, $fieldTypes)) {
				$fieldTypes[$metric] = $type;
			}
		}

		foreach ($dataSet->getDimensions() as $dimension => $type) {
			$dimension = sprintf('%s_%d', $dimension, $dataSet->getId());

            // add new fieldType mapping if not existed
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
		$showInTotals = is_array($showInTotals) ? $showInTotals : [$showInTotals];

		foreach ($showInTotals as &$showInTotal) {
			if (!array_key_exists('fields', $showInTotal)) {
				continue;
			}
			$fields = $showInTotal['fields'];
			$missingFields = array_diff($fields, $allDimensionsMetrics);
			$fields = $this->removeItemsFromArray($fields, $missingFields);
			$showInTotal['fields'] = array_values($fields);
		}

		return array_values($showInTotals);
	}

	/**
	 * @param ReportViewDataSetInterface $reportViewDataSet
	 * @param array $newFields
	 * @param array $updatedFields
	 * @param array $deletedFields
	 */
	private function updateReportViewDataSet(ReportViewDataSetInterface $reportViewDataSet, array $newFields, array $updatedFields, array $deletedFields)
	{
		$newDimensions = $reportViewDataSet->getDimensions();
		$newMetrics = $reportViewDataSet->getMetrics();
		$changed = false;

		// Update when have newFields
		// => No need update dimensions, metrics for reportViewDataSet if new fields
		// Because this should be selected manually from UI

		// Update when have updatedFields
		if (!empty($updatedFields)) {
			foreach ($updatedFields as $from => $to) {
				if (in_array($from, $newDimensions)) {
					$changed = true;

					// add new
					$newDimensions[] = $to;

					// remove old
					if (($idx = array_search($from, $newDimensions)) !== false) {
						unset($newDimensions[$idx]);
					}
				}

				if (in_array($from, $newMetrics)) {
					$changed = true;

					// add new
					$newMetrics[] = $to;

					// remove old
					if (($idx = array_search($from, $newMetrics)) !== false) {
						unset($newMetrics[$idx]);
					}
				}
			}
		}

		// Update when have deletedFields
		if (!empty($deletedFields)) {
			foreach ($deletedFields as $deletedField => $type) {
				if (in_array($deletedField, $newDimensions)) {
					$changed = true;

					// remove old
					if (($idx = array_search($deletedField, $newDimensions)) !== false) {
						unset($newDimensions[$idx]);
					}
				}

				if (in_array($deletedField, $newMetrics)) {
					$changed = true;

					// remove old
					if (($idx = array_search($deletedField, $newMetrics)) !== false) {
						unset($newMetrics[$idx]);
					}
				}
			}
		}

		if ($changed) {
			$reportViewDataSet->setDimensions(array_values($newDimensions));
			$reportViewDataSet->setMetrics(array_values($newMetrics));
			$this->em->merge($reportViewDataSet);
		}
	}
}