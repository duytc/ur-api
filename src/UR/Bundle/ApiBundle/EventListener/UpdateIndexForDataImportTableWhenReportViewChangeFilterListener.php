<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Service\DataSet\DataSetTableUtil;

class UpdateIndexForDataImportTableWhenReportViewChangeFilterListener
{
	protected $changedReportViews;

    protected $dataSetTableUtil;

    /**
     * UpdateIndexForDataImportTAbleWhenReportViewListener constructor.
     * @param DataSetTableUtil $dataSetTableUtil
     */
	public function __construct(DataSetTableUtil $dataSetTableUtil)
	{
		$this->dataSetTableUtil = $dataSetTableUtil;
	}

	public function prePersist(LifecycleEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewDataSetInterface) {
			return;
		}

		$this->changedReportViews[] = $entity;
	}

	public function preUpdate(PreUpdateEventArgs $args)
	{
		$entity = $args->getEntity();

		if (!$entity instanceof ReportViewDataSetInterface) {
			return;
		}

		if ($args->hasChangedField('filters')) {
			$this->changedReportViews[] = $entity;
		}
	}

	public function postFlush(PostFlushEventArgs $args)
	{
		$em = $args->getEntityManager();
		if (empty($this->changedReportViews)) {
			return;
		}

		foreach($this->changedReportViews as $reportViewDataSet) {

            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

			$this->dataSetTableUtil->updateIndexesByFilter($reportViewDataSet);
            // update index for data import table
		}

		$this->changedReportViews = [];

		$em->flush();
	}
}