<?php
namespace UR\Service\Report;


use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewMultiViewInterface;

class CloneReportView implements CloneReportViewInterface
{
    protected $reportViewManager;

    public function __construct(ReportViewManagerInterface $reportViewManager)
    {
        $this->reportViewManager = $reportViewManager;
    }

    public function cloneReportView(ReportViewInterface $reportView, array $cloneSettings)
    {
        foreach ($cloneSettings as $cloneSetting) {
            $newReportView = clone $reportView;
            $newName = array_key_exists(self::CLONE_REPORT_VIEW_NAME, $cloneSetting) ? $cloneSetting[self::CLONE_REPORT_VIEW_NAME] : $reportView->getName();
            $newAlias = array_key_exists(self::CLONE_REPORT_VIEW_ALIAS, $cloneSetting) ? $cloneSetting[self::CLONE_REPORT_VIEW_ALIAS] : $cloneSetting[self::CLONE_REPORT_VIEW_NAME];
            $newReportView->setName($newName === null ? $reportView->getName() : $newName);
            $newReportView->setAlias($newAlias === null ? $newName : $newAlias);
            $newReportViewDataSetJson = [];
            if (array_key_exists('reportViewDataSets', $cloneSettings)) {
                $newReportViewDataSetJson = $cloneSetting['reportViewDataSets'];
                $newTransforms = array_key_exists(self::CLONE_REPORT_VIEW_TRANSFORM, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_TRANSFORM] : [];
                $newFormats = array_key_exists(self::CLONE_REPORT_VIEW_FORMAT, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_FORMAT] : [];
                $newReportView->setTransforms($newTransforms);
                $newReportView->setFormats($newFormats);
            }

            // clone filters
            /** @var ReportViewDataSetInterface[] $reportViewDataSets */
            /** @var ReportViewMultiViewInterface[] $reportViewMultiViews */
            $reportViewDataSets = $reportView->getReportViewDataSets();
            $reportViewMultiViews = $reportView->getReportViewMultiViews();
            $newReportViewDataSets = [];
            $newReportViewMultiViews = [];
            if (!$reportView->isMultiView()) {
                foreach ($reportViewDataSets as $reportViewDataSet) {
                    $newReportViewDataSet = clone $reportViewDataSet;
                    $newReportViewDataSet->setReportView($newReportView);
                    // process with $newReportViewDataSetJson
                    foreach ($newReportViewDataSetJson as $item) {
                        if (!array_key_exists(self::CLONE_REPORT_VIEW_DATA_SET, $item)) {
                            throw new \Exception('message should contains % key', self::CLONE_REPORT_VIEW_DATA_SET);
                        }

                        if ($newReportViewDataSet->getDataSet()->getId() === $item[self::CLONE_REPORT_VIEW_DATA_SET]) {
                            $newReportViewDataSet->setFilters($item['filters']);
                        }

                        continue;
                    }

                    $newReportViewDataSets[] = $newReportViewDataSet;
                }
            } else {

                foreach ($reportViewMultiViews as $reportViewMultiView) {
                    $newReportViewMultiView = clone $reportViewMultiView;
                    $newReportViewMultiView->setReportView($newReportView);
                    // process with $newReportViewDataSetJson
                    foreach ($newReportViewDataSetJson as $item) {
                        if (!array_key_exists(self::CLONE_REPORT_VIEW_SUB_VIEW, $item)) {
                            throw new \Exception('message should contains % key', self::CLONE_REPORT_VIEW_SUB_VIEW);
                        }

                        if ($newReportViewMultiView->getSubView()->getId() === $item[self::CLONE_REPORT_VIEW_SUB_VIEW]) {
                            $newReportViewMultiView->setFilters($item['filters']);
                        }

                        continue;
                    }

                    $newReportViewMultiViews[] = $newReportViewMultiView;
                }
            }

            $newReportView->setReportViewDataSets($newReportViewDataSets);
            $newReportView->setReportViewMultiViews($newReportViewMultiViews);
            $this->reportViewManager->save($newReportView);
        }
    }
}