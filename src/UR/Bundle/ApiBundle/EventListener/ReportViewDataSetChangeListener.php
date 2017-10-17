<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Worker\Manager;

class ReportViewDataSetChangeListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';

	/**
	 * @var ParamsBuilderInterface
	 * @var Manager
	 */
	protected $paramsBuilder;

	protected $changedReportViews;

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

		if (!$entity instanceof ReportViewDataSetInterface) {
			return;
		}

		$this->changedReportViews[] = $entity->getReportView();
	}

	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewDataSetInterface) {
			return;
		}

		if ($args->hasChangedField('dimensions') || $args->hasChangedField('metrics')) {
			$this->changedReportViews[] = $entity->getReportView();
		}
	}

	public function postFlush(PostFlushEventArgs $args)
	{
		$em = $args->getEntityManager();
		if (empty($this->changedReportViews)) {
			return;
		}

		foreach($this->changedReportViews as $reportView) {
			if (!$reportView instanceof ReportViewInterface) {
				continue;
			}

			$param = $this->paramsBuilder->buildFromReportView($reportView);
			$columns = $this->getMetricsAndDimensionsForSingleView($param);

			$reportView->setMetrics($columns[self::METRICS_KEY]);
			$reportView->setDimensions($columns[self::DIMENSIONS_KEY]);

			$em->persist($reportView);
		}

		$this->changedReportViews = [];

		$em->flush();
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