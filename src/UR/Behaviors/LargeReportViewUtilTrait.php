<?php


namespace UR\Behaviors;


use Doctrine\Common\Collections\Collection;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;

trait LargeReportViewUtilTrait
{
    /**
     * @param ReportViewInterface $reportView
     * @param $largeThreshold
     * @return bool
     */
    public function isLargeReportView(ReportViewInterface $reportView, $largeThreshold)
    {
        if (!$reportView->isLargeReport()) {
            return false;
        }
        
        $rpDataSetsCollection = $reportView->getReportViewDataSets();
        if ($rpDataSetsCollection instanceof Collection) {
            $rpDataSetsCollection = $rpDataSetsCollection->toArray();
        }

        $rpDataSets = is_array($rpDataSetsCollection) ? $rpDataSetsCollection : [];
        $dataSets = array_map(function ($rpDataSet) {
            if ($rpDataSet instanceof ReportViewDataSetInterface) {
                return $rpDataSet->getDataSet();
            }
        }, $rpDataSets);

        $totalRows = 1;
        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $totalRows = $totalRows * $dataSet->getTotalRow();
        }

        return $totalRows >= $largeThreshold;
    }
}