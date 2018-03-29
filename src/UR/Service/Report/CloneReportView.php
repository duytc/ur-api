<?php
namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\ReportViewAddConditionalTransformValueManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;

class CloneReportView implements CloneReportViewInterface
{
    protected $reportViewManager;
    /** @var ReportViewAddConditionalTransformValueManagerInterface */
    protected $conditionalTransformManager;

    public function __construct(ReportViewManagerInterface $reportViewManager, ReportViewAddConditionalTransformValueManagerInterface $conditionalTransformManager)
    {
        $this->reportViewManager = $reportViewManager;
        $this->conditionalTransformManager = $conditionalTransformManager;
    }

    public function cloneReportView(ReportViewInterface $reportView, array $cloneSettings)
    {
        foreach ($cloneSettings as $cloneSetting) {
            $newReportView = clone $reportView;
            $newName = array_key_exists(self::CLONE_REPORT_VIEW_NAME, $cloneSetting) ? $cloneSetting[self::CLONE_REPORT_VIEW_NAME] : $reportView->getName();
            $newReportView->setName($newName === null ? $reportView->getName() : $newName);
            $newReportViewDataSetJson = [];
            if (array_key_exists('reportViewDataSets', $cloneSettings)) {
                $newReportViewDataSetJson = $cloneSetting['reportViewDataSets'];
                $newTransforms = array_key_exists(self::CLONE_REPORT_VIEW_TRANSFORM, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_TRANSFORM] : [];
                $newFormats = array_key_exists(self::CLONE_REPORT_VIEW_FORMAT, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_FORMAT] : [];
                $newReportView->setTransforms($newTransforms);
                $newReportView->setFormats($newFormats);
            }

            //clone transforms
            $transforms = $reportView->getTransforms();
            foreach ($transforms as &$transform) {
                if ($transform[TransformInterface::TRANSFORM_TYPE_KEY] == AddConditionValueTransform::TRANSFORMS_TYPE) {
                    $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                    foreach ($fields as &$field) {
                        $values = $field[AddConditionValueTransform::VALUES_KEY];
                        foreach ($values as &$conditionalTransformId) {
                            $transformValue = $this->conditionalTransformManager->find($conditionalTransformId);
                            if (!$transformValue instanceof ReportViewAddConditionalTransformValueInterface) {
                                continue;
                            }

                            $newTransformValue = clone $transformValue;
                            $newTransformValue->setId(null);
                            $this->conditionalTransformManager->save($newTransformValue);
                            $conditionalTransformId = $newTransformValue->getId();
                        }
                        unset($conditionalTransformId);
                        $field[AddConditionValueTransform::VALUES_KEY] = $values;
                    }

                    unset($field);
                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                }
            }
            unset($transform);
            $newReportView->setTransforms($transforms);

            // clone filters
            /** @var ReportViewDataSetInterface[] $reportViewDataSets */
            $reportViewDataSets = $reportView->getReportViewDataSets();
            $newReportViewDataSets = [];
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

            $newReportView->setReportViewDataSets($newReportViewDataSets);
            $this->reportViewManager->save($newReportView);
        }
    }
}