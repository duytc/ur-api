<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\UpdateReportViewTrait;
use UR\Entity\Core\ReportView;
use UR\Entity\Core\ReportViewDataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewDataSetRepositoryInterface;
use UR\Service\Report\SqlBuilder;

class DataSetChangeListener
{
    use UpdateReportViewTrait;
	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';
	const FIELD_TYPES_KEY = 'fieldTypes';
	const TRANSFORMS_KEY = 'transforms';
	const FORMATS_KEY = 'formats';
	const JOIN_CONFIG_KEY = 'joinConfigs';

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

		if (count($args->getEntityChangeSet()) == 1 && $args->hasChangedField('numOfPendingLoad')) {
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
		$reportViewRepository = $em->getRepository(ReportView::class);
		if (empty($this->changedDataSets)) {
			return;
		}

		foreach($this->changedDataSets as $dataSet) {
			if (!$dataSet instanceof DataSetInterface) {
				continue;
			}
            $reportViews = $reportViewRepository->getReportViewThatUseDataSet($dataSet);
			/** @var ReportViewInterface $reportView */
			foreach($reportViews as $reportView) {
				$result = $this->refreshSingleReportView($reportView, $dataSet, $reportViewDataSetRepository);

				$reportView->setDimensions($result[self::DIMENSIONS_KEY]);
				$reportView->setMetrics($result[self::METRICS_KEY]);
				$reportView->setFieldTypes($result[self::FIELD_TYPES_KEY]);
                $reportView->setTransforms($result[self::TRANSFORMS_KEY]);
                $reportView->setFormats($result[self::FORMATS_KEY]);
                $reportView->setJoinBy($result[self::JOIN_CONFIG_KEY]);

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
	protected function refreshSingleReportView(ReportViewInterface $reportView, DataSetInterface $changedDataSet, ReportViewDataSetRepositoryInterface $reportViewDataSetRepository)
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

        $allDimensionsMetrics = array_merge($metrics, $dimensions);
		$transforms = $this->refreshTransformsForSingleReportView($reportView, $allDimensionsMetrics);
		$formats = $this->refreshFormatsForSingleReportView($reportView, $allDimensionsMetrics);
        $joinConfigs = $this->refreshJoinConfigsForSingleReportView($reportView, $allDimensionsMetrics);

		return array(
			self::METRICS_KEY => $metrics,
			self::DIMENSIONS_KEY => $dimensions,
			self::FIELD_TYPES_KEY => $fieldTypes,
			self::TRANSFORMS_KEY => $transforms,
			self::FORMATS_KEY => $formats,
            self::JOIN_CONFIG_KEY => $joinConfigs
		);
	}

    /**
     * @param ReportViewInterface $reportView
     * @param array $allDimensionsMetrics
     * @return array
     */
	protected function refreshTransformsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
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


	protected function refreshFormatsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
	{
        $formats = $reportView->getFormats();
        foreach($formats as $i=>$format) {
            $validFormat = $this->refreshFormat($format, $allDimensionsMetrics);
            if ($validFormat === null) {
                unset($formats[$i]);
            } else {
                $formats[$i] = $validFormat;
            }
        }

		return array_values($formats);
	}

    protected function refreshJoinConfigsForSingleReportView(ReportViewInterface $reportView, array $allDimensionsMetrics)
    {
        $joinConfigs = $reportView->getJoinBy();
        foreach($joinConfigs as $i=>$joinConfig) {
            $joinConfigs[$i] = $this->refreshJoinConfig($joinConfig, $allDimensionsMetrics);
        }

        return $joinConfigs;
    }

    protected function refreshJoinConfig(array $joinConfig, $allDimensionsMetrics)
    {
        $joinFields = $joinConfig[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
        foreach($joinFields as $i=>&$joinField) {
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