<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Service\Report\ParamsBuilderInterface;

class ReportViewMultiViewChangeListener
{
	use CalculateMetricsAndDimensionsTrait;

	const METRICS_KEY = 'metrics';
	const DIMENSIONS_KEY = 'dimensions';

	/**
	 * @var ParamsBuilderInterface
	 */
	private $paramsBuilder;

	private $changedReportViews = [];


	/**
	 * UpdateMetricsAndDimensionsForMultipleViewReport constructor.
	 * @param ParamsBuilderInterface $paramsBuilder
	 */
	public function __construct(ParamsBuilderInterface $paramsBuilder)
	{
		$this->paramsBuilder = $paramsBuilder;
	}

	public function prePersist(LifecycleEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewMultiViewInterface) {
			return;
		}

		$this->changedReportViews[] = $entity->getReportView();
	}

	/**
	 * @param PreUpdateEventArgs $args
	 */
	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewMultiViewInterface) {
			return;
		}

		if ($args->hasChangedField('dimensions') || $args->hasChangedField('metrics')) {
			$this->changedReportViews[] = $entity->getReportView();
		}
	}

	/**
	 * @param PostFlushEventArgs $args
	 */
	public function postFlush(PostFlushEventArgs $args)
	{
		$em = $args->getEntityManager();
		if (empty($this->changedReportViews)) {
			return;
		}

		/**
		 * @var ReportViewInterface $reportView
		 */
		foreach($this->changedReportViews as $reportView) {
			if (!$reportView instanceof ReportViewInterface || ($reportView instanceof ReportViewInterface && $reportView->isMultiView() === false)) {
				continue;
			}

			$param = $this->paramsBuilder->buildFromReportView($reportView);
			$columns = $this->getMetricsAndDimensionsForMultiView($param);

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