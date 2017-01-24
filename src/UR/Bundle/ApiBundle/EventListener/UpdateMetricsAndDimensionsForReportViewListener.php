<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Worker\Manager;

class UpdateMetricsAndDimensionsForReportViewListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';

	/**
	 * @var ParamsBuilderInterface
	 * @var Manager
	 */
	protected $paramsBuilder;

	/**
	 * UpdateMetricsAndDimensionsForReportViewListener constructor.
	 * @param ParamsBuilderInterface $paramsBuilder
	 */
	public function __construct(ParamsBuilderInterface $paramsBuilder)
	{
		$this->paramsBuilder = $paramsBuilder;
	}


	public function prePersist(LifecycleEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewInterface) {
			return;
		}

		$this->updateMetricsAndDimensionsForReportView($entity);
	}

	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewInterface) {
			return;
		}

		if ($args->hasChangedField('transforms')) {
			$this->updateMetricsAndDimensionsForReportView($entity);
		}
	}

	protected function updateMetricsAndDimensionsForReportView(ReportViewInterface $reportView)
	{
		$param = $this->paramsBuilder->buildFromReportView($reportView);
		if ($reportView->isMultiView()) {
			$columns = $this->getMetricsAndDimensionsForMultiView($param);
		} else {
			$columns = $this->getMetricsAndDimensionsForSingleView($param);
		}

		$fieldTypes = $reportView->getFieldTypes();
		foreach($fieldTypes as $field=>$type) {
			if (!in_array($field, $columns[self::METRICS_KEY]) && !in_array($field, $columns[self::DIMENSIONS_KEY])) {
				unset($fieldTypes[$field]);
			}
		}
		$reportView->setMetrics($columns[self::METRICS_KEY]);
		$reportView->setDimensions($columns[self::DIMENSIONS_KEY]);
		$reportView->setFieldTypes($fieldTypes);
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