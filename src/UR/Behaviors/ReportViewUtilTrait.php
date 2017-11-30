<?php

namespace UR\Behaviors;

use Doctrine\Common\Collections\Collection;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\SqlBuilder;
use UR\Service\StringUtilTrait;

trait ReportViewUtilTrait
{
    use StringUtilTrait;

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function getFiltersFromReportView(ReportViewInterface $reportView)
    {
        /** Get all filters from report view */
        $filters = [];
        $rpDataSets = ($reportView->isSubView()) ? $reportView->getMasterReportView()->getReportViewDataSets() : $reportView->getReportViewDataSets();
        if ($rpDataSets instanceof Collection) {
            $rpDataSets = $rpDataSets->toArray();
        }

        foreach ($rpDataSets as $rpDataSet) {
            if (!$rpDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $filters = array_merge($filters, $rpDataSet->getFilters());
        }

        $subViewFilters = $reportView->getFilters();

        if (!is_array($subViewFilters)) {
            $subViewFilters = [];
        }

        return array_merge($filters, $subViewFilters);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    public function getFieldsFromReportView(ReportViewInterface $reportView)
    {
        /**
         * Field on report view come from 2 sources:
         *  - For single view: Get fields from data sets, Add New Field Transforms
         *  - For multi views: Get fields from sub views, Add New Field Transforms
         */

        $fields = [];

        $fields = array_merge($fields, $this->getNewFieldsFromTransforms($reportView->getTransforms()));
        $fields = array_merge($fields, $reportView->getDimensions(), $reportView->getMetrics());

        $rpDataSets = $reportView->getReportViewDataSets();
        if ($rpDataSets instanceof Collection) {
            $rpDataSets = $rpDataSets->toArray();
        }

        foreach ($rpDataSets as $rpDataSet) {
            if (!$rpDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $fields = array_merge($fields, $rpDataSet->getDimensions(), $rpDataSet->getMetrics());
        }

        $fields = $this->removeInvisibleFieldInReplaceTextTransforms($reportView, $fields);

        $fields = $this->removeInvisibleOutputJoinFields($reportView, $fields);

        return $fields;
    }

    /**
     * @param ReportViewInterface $reportView
     * @param $allFields
     * @return array
     */
    public function removeInvisibleFieldInReplaceTextTransforms(ReportViewInterface $reportView, $allFields)
    {
        $transforms = $reportView->getTransforms();

        if (!is_array($transforms) || count($transforms) < 1) {
            return $allFields;
        }

        foreach ($transforms as $transform) {
            if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform)) {
                continue;
            }
            $type = $transform[TransformInterface::TRANSFORM_TYPE_KEY];

            if (in_array($type, [ReplaceTextTransform::TRANSFORMS_TYPE])) {
                if (!array_key_exists(TransformInterface::FIELDS_TRANSFORM, $transform)) {
                    continue;
                }
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];

                foreach ($fields as $field) {
                    if (!array_key_exists(ReplaceTextTransform::IS_OVERRIDE_KEY, $field)) {
                        continue;
                    }
                    $isOverwrite = $field[ReplaceTextTransform::IS_OVERRIDE_KEY];
                    /**
                     * Field on Replace Text already exist on metrics.
                     *    - If Overwrite = true, we use exist field, so replace text field need to remove
                     *    - If Overwrite = false, we use replace text field, do nothing for this
                     */
                    if ($isOverwrite) {
                        if (!array_key_exists(ReplaceTextTransform::TARGET_FIELD_KEY, $field)) {
                            continue;
                        }
                        $targetField = $field[ReplaceTextTransform::TARGET_FIELD_KEY];

                        foreach ($allFields as $key => $existField) {
                            if ($existField == $targetField) {
                                unset($allFields[$key]);
                            }
                        }
                    }
                }
            }
        }

        return array_values($allFields);
    }

    /**
     * @param ReportViewInterface $reportView
     * @param $allFields
     * @return array
     */
    public function removeInvisibleOutputJoinFields(ReportViewInterface $reportView, $allFields)
    {
        $joinBys = $reportView->getJoinBy();

        if (!is_array($joinBys) || count($joinBys) < 1) {
            return $allFields;
        }

        $flags = [];

        foreach ($joinBys as $joinBy) {
            if (!array_key_exists(SqlBuilder::JOIN_CONFIG_VISIBLE, $joinBy) ||
                !array_key_exists(SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD, $joinBy)
            ) {
                continue;
            }

            $outputJoinField = $joinBy[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD];
            $visible = $joinBy[SqlBuilder::JOIN_CONFIG_VISIBLE];

            if (!array_key_exists($outputJoinField, $flags)) {
                $flags[$outputJoinField] = $visible;
            } else {
                $flags[$outputJoinField] = $flags[$outputJoinField] || $visible;
            }
        }

        foreach ($flags as $outputJoinField => $visible) {
            if ($visible) {
                continue;
            }

            if (!in_array($outputJoinField, $allFields)) {
                continue;
            }

            foreach ($allFields as $index => $allField) {
                if ($outputJoinField == $allField) {
                    unset($allFields[$index]);
                }
            }
        }

        return array_values($allFields);
    }
}