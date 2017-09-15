<?php


namespace UR\Service\ReportViewTemplate;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Bundle\ApiBundle\EventListener\ReportViewMultiViewChangeListener;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewDataSetManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\DomainManager\ReportViewMultiViewManagerInterface;
use UR\DomainManager\ReportViewTemplateManagerInterface;
use UR\DomainManager\ReportViewTemplateTagManagerInterface;
use UR\DomainManager\TagManagerInterface;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ReportView;
use UR\Entity\Core\ReportViewDataSet;
use UR\Entity\Core\ReportViewMultiView;
use UR\Entity\Core\ReportViewTemplateTag;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Entity\Core\ReportViewTemplate;
use UR\Model\Core\ReportViewMultiViewInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Entity\Core\Tag;
use UR\Model\Core\TagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\SqlBuilder;
use UR\Service\ReportViewTemplate\DTO\CustomTemplateParamsInterface;

class ReportViewTemplateService implements ReportViewTemplateServiceInterface
{
    use CalculateMetricsAndDimensionsTrait;

    /** @var  ReportViewManagerInterface */
    protected $reportViewManager;

    /** @var  ReportViewTemplateManagerInterface */
    protected $reportViewTemplateManager;

    /** @var TagManagerInterface */
    protected $tagManager;

    /** @var ReportViewTemplateTagManagerInterface */
    protected $reportViewTemplateTagManager;

    /** @var  EntityManagerInterface */
    protected $em;

    /** @var ParamsBuilderInterface */
    protected $paramsBuilder;

    /** @var array */
    protected $replaceDataSetId = [];

    /** @var ReportViewMultiViewManagerInterface  */
    protected $reportViewMultiViewManager;

    /** @var ReportViewDataSetManagerInterface  */
    protected $reportViewDataSetManager;

    /** @var DataSetManagerInterface  */
    protected $dataSetManager;

    /**
     * ReportViewTemplateService constructor.
     * @param ReportViewManagerInterface $reportViewManager
     * @param ReportViewTemplateManagerInterface $reportViewTemplateManager
     * @param TagManagerInterface $tagManager
     * @param ReportViewTemplateTagManagerInterface $reportViewTemplateTagManager
     * @param EntityManagerInterface $em
     * @param ParamsBuilderInterface $paramsBuilder
     * @param ReportViewMultiViewManagerInterface $reportViewMultiViewManager
     * @param ReportViewDataSetManagerInterface $reportViewDataSetManager
     * @param DataSetManagerInterface $dataSetManager
     */
    public function __construct(ReportViewManagerInterface $reportViewManager, ReportViewTemplateManagerInterface $reportViewTemplateManager, TagManagerInterface $tagManager, ReportViewTemplateTagManagerInterface $reportViewTemplateTagManager, EntityManagerInterface $em, ParamsBuilderInterface $paramsBuilder, ReportViewMultiViewManagerInterface $reportViewMultiViewManager, ReportViewDataSetManagerInterface $reportViewDataSetManager, DataSetManagerInterface $dataSetManager)
    {
        $this->reportViewManager = $reportViewManager;
        $this->reportViewTemplateManager = $reportViewTemplateManager;
        $this->tagManager = $tagManager;
        $this->reportViewTemplateTagManager = $reportViewTemplateTagManager;
        $this->em = $em;
        $this->paramsBuilder = $paramsBuilder;
        $this->reportViewMultiViewManager = $reportViewMultiViewManager;
        $this->reportViewDataSetManager = $reportViewDataSetManager;
        $this->dataSetManager = $dataSetManager;
    }

    /**
     * @inheritdoc
     */
    public function createReportViewTemplateFromReportView(ReportViewInterface $reportView, CustomTemplateParamsInterface $customTemplateParams)
    {
        $template = new ReportViewTemplate();

        $this->inheritedTemplateFromReportView($template, $reportView);

        $this->inheritedTemplateFromCustomParams($template, $customTemplateParams);

        $this->reportViewTemplateManager->save($template);

        /** Create report view template tags */
        if (empty($customTemplateParams->getTags())) {
            return;
        }

        foreach ($customTemplateParams->getTags() as $tagName) {
            $tag = $this->tagManager->findByName($tagName);
            if (!$tag instanceof TagInterface) {
                $tag = new Tag();
                $tag->setName($tagName);
                $this->tagManager->save($tag);
            }

            $reportViewTemplateTag = new ReportViewTemplateTag();
            $reportViewTemplateTag->setTag($tag);
            $reportViewTemplateTag->setReportViewTemplate($template);
            $this->reportViewTemplateTagManager->save($reportViewTemplateTag);
        }
    }

    /**
     * @inheritdoc
     */
    public function createReportViewFromReportViewTemplate(ReportViewTemplateInterface $template, PublisherInterface $publisher, CustomTemplateParamsInterface $customParams)
    {
        $reportView = new ReportView();
        $reportView->setPublisher($publisher);

        /** Inherited bool value*/
        $reportView->setMultiView($template->isMultiView());
        $reportView->setIsShowDataSetName($template->isShowDataSetName());
        $reportView->setName($template->getName());
        $reportView->setAlias($template->getName());
        $reportView->setSubReportsIncluded($template->isMultiView());

        /** Inherited array value */
        $reportView->setDimensions($template->getDimensions());
        $reportView->setMetrics($template->getMetrics());
        $reportView->setTransforms($template->getTransforms());
        $reportView->setFormats($template->getFormats());
        $reportView->setJoinBy($template->getJoinConfig());
        $reportView->setShowInTotal($template->getShowInTotal());

        /** Convert report views multi views and report view data sets */
        $reportView->setReportViewMultiViews($this->convertArrayToReportViewMultiViews($template->getReportViews(), $publisher));
        $reportView->setReportViewDataSets($this->convertArrayToReportViewDataSets($template->getDataSets(), $publisher));

        /** Inherited value from custom params */
        if (!empty($customParams->getName())) {
            $reportView->setName($customParams->getName());
        }

        $this->correctFieldsInReportView($reportView);

        $this->reportViewManager->save($reportView);

        $this->setReportViewForReportViewDataSets($reportView);
        $this->setReportViewForReportViewMultiViews($reportView);
    }

    /**
     * @param ReportViewTemplateInterface $template
     * @param ReportViewInterface $reportView
     * @return ReportViewTemplateInterface
     */
    private function inheritedTemplateFromReportView(ReportViewTemplateInterface $template, ReportViewInterface $reportView)
    {
        /** Inherited bool value*/
        $template->setMultiView($reportView->isMultiView());
        $template->setShowDataSetName($reportView->getIsShowDataSetName());
        $template->setName($reportView->getName());

        /** Inherited array value */
        $template->setDimensions($reportView->getDimensions());
        $template->setMetrics($reportView->getMetrics());
        $template->setTransforms($reportView->getTransforms());
        $template->setFormats($reportView->getFormats());
        $template->setJoinConfig($reportView->getJoinBy());
        $template->setShowInTotal($reportView->getShowInTotal());

        /** Convert sub report views and data sets to array in template */
        $template->setReportViews($this->convertReportViewMultiViewsToArray($reportView->getReportViewMultiViews()));
        $template->setDataSets($this->convertReportViewDataSetsToArray($reportView->getReportViewDataSets()));

        return $template;
    }

    /**
     * @param ReportViewTemplateInterface $template
     * @param CustomTemplateParamsInterface $customTemplateParams
     * @return ReportViewTemplateInterface
     */
    private function inheritedTemplateFromCustomParams(ReportViewTemplateInterface $template, CustomTemplateParamsInterface $customTemplateParams)
    {
        if (!empty($customTemplateParams->getName())) {
            $template->setName($customTemplateParams->getName());
        }

        return $template;
    }

    /**
     * @param Collection $reportViewMultiViews
     * @return array
     */
    private function convertReportViewMultiViewsToArray(Collection $reportViewMultiViews)
    {
        $reportViewMultiViews = $reportViewMultiViews->toArray();

        if (empty($reportViewMultiViews)) {
            return [];
        }

        $reportViews = [];

        foreach ($reportViewMultiViews as $reportViewMultiView) {
            if (!$reportViewMultiView instanceof ReportViewMultiViewInterface) {
                continue;
            }

            $reportViews[] = [
                ModelInterface::ID => $reportViewMultiView->getSubView()->getId(),
                ReportViewInterface::REPORT_VIEW_FILTERS => $reportViewMultiView->getFilters(),
                ReportViewInterface::REPORT_VIEW_DIMENSIONS => $reportViewMultiView->getDimensions(),
                ReportViewInterface::REPORT_VIEW_METRICS => $reportViewMultiView->getMetrics()
            ];
        }

        return $reportViews;
    }

    /**
     * @param Collection $reportViewDataSets
     * @return array
     */
    private function convertReportViewDataSetsToArray(Collection $reportViewDataSets)
    {
        $reportViewDataSets = $reportViewDataSets->toArray();

        if (empty($reportViewDataSets)) {
            return [];
        }

        $dataSets = [];

        foreach ($reportViewDataSets as $reportViewDataSet) {
            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $dataSet = $reportViewDataSet->getDataSet();

            $dataSets[$dataSet->getId()] = [
                ReportViewInterface::REPORT_VIEW_DIMENSIONS => $reportViewDataSet->getDimensions(),
                ReportViewInterface::REPORT_VIEW_METRICS => $reportViewDataSet->getMetrics(),
                ReportViewInterface::REPORT_VIEW_FILTERS => $reportViewDataSet->getFilters(),
                DataSetInterface::NAME_COLUMN => $dataSet->getName(),
                DataSetInterface::ALLOW_OVERWRITE_EXISTING_DATA => $dataSet->getAllowOverwriteExistingData(),
                DataSetInterface::DIMENSIONS_COLUMN => $dataSet->getDimensions(),
                DataSetInterface::METRICS_COLUMN => $dataSet->getMetrics(),
            ];
        }

        return $dataSets;
    }

    /**
     * @param array $reportViews
     * @param PublisherInterface $publisher
     * @return \UR\Model\Core\ReportViewMultiViewInterface[]
     */
    private function convertArrayToReportViewMultiViews(array $reportViews, PublisherInterface $publisher)
    {
        $reportViewMultiViews = [];

        foreach ($reportViews as $data) {
            $id = $data[ReportViewInterface::ID];
            $oldSubView = $this->reportViewManager->find($id);
            if (!$oldSubView instanceof ReportViewInterface) {
                continue;
            }

            $subView = $this->cloneReportView($oldSubView, $publisher);

            $reportViewMultiView = new ReportViewMultiView();
            $reportViewMultiView->setSubView($subView);
            if (array_key_exists(ReportViewInterface::REPORT_VIEW_FILTERS, $data)) {
                $reportViewMultiView->setFilters($data[ReportViewInterface::REPORT_VIEW_FILTERS]);
            }

            $reportViewMultiView->setEnableCustomDimensionMetric(false);
            $reportViewMultiView->setDimensions($data[ReportViewInterface::REPORT_VIEW_DIMENSIONS]);
            $reportViewMultiView->setMetrics($data[ReportViewInterface::REPORT_VIEW_METRICS]);
            $this->correctFieldsInReportViewMultiView($reportViewMultiView);

            $this->em->persist($reportViewMultiView);
            $this->em->flush();

            $reportViewMultiViews[] = $reportViewMultiView;
        }

        return $reportViewMultiViews;
    }

    /**
     * @param array $dataSets
     * @param PublisherInterface $publisher
     * @return \UR\Model\Core\ReportViewDataSetInterface[]
     */
    private function convertArrayToReportViewDataSets(array $dataSets, PublisherInterface $publisher)
    {
        $reportViewDataSets = [];

        foreach ($dataSets as $oldId => $data) {
            $dataSet = new DataSet();
            $dataSet->setPublisher($publisher);

            if (array_key_exists(DataSetInterface::DIMENSIONS_COLUMN, $data)) {
                $dataSet->setDimensions($data[DataSetInterface::DIMENSIONS_COLUMN]);
            }

            if (array_key_exists(DataSetInterface::METRICS_COLUMN, $data)) {
                $dataSet->setMetrics($data[DataSetInterface::METRICS_COLUMN]);
            }

            if (array_key_exists(DataSetInterface::ALLOW_OVERWRITE_EXISTING_DATA, $data)) {
                $dataSet->setAllowOverwriteExistingData($data[DataSetInterface::ALLOW_OVERWRITE_EXISTING_DATA]);
            }

            if (array_key_exists(DataSetInterface::NAME_COLUMN, $data)) {
                $dataSet->setName($data[DataSetInterface::NAME_COLUMN]);
            }

            $dataSet->setMapBuilderEnabled(false);

            $this->em->persist($dataSet);
            $this->em->flush();

            $reportViewDataSet = new ReportViewDataSet();

            if (array_key_exists(ReportViewInterface::REPORT_VIEW_FILTERS, $data)) {
                $reportViewDataSet->setFilters($data[ReportViewInterface::REPORT_VIEW_FILTERS]);
            }

            if (array_key_exists(ReportViewInterface::REPORT_VIEW_DIMENSIONS, $data)) {
                $reportViewDataSet->setDimensions($data[ReportViewInterface::REPORT_VIEW_DIMENSIONS]);
            }


            if (array_key_exists(ReportViewInterface::REPORT_VIEW_METRICS, $data)) {
                $reportViewDataSet->setMetrics($data[ReportViewInterface::REPORT_VIEW_METRICS]);
            }

            $reportViewDataSet->setDataSet($dataSet);
            $this->correctFieldsInReportViewDataSet($reportViewDataSet);

            $this->em->persist($reportViewDataSet);
            $this->em->flush();

            $reportViewDataSets[] = $reportViewDataSet;

            /** Important oldId and newId need to store and used in $this->correctFieldsInReportView() */
            $this->replaceDataSetId[$oldId] = $dataSet->getId();
        }

        return $reportViewDataSets;
    }

    /**
     * Many fields in report view contain old data set id
     * They need to update with new data set id
     *
     * Example impression_4 need to update to impression_169
     *
     * @param ReportViewInterface $reportView
     */
    private function correctFieldsInReportView(ReportViewInterface $reportView)
    {
        $dimensions = $this->updateDataSetId($reportView->getDimensions());
        $reportView->setDimensions($dimensions);

        $metrics = $this->updateDataSetId($reportView->getMetrics());
        $reportView->setMetrics($metrics);

        $showInTotal = $this->updateDataSetId($reportView->getShowInTotal());
        $reportView->setShowInTotal($showInTotal);

        $formats = $this->updateDataSetId($reportView->getFormats());
        $reportView->setFormats($formats);

        $transforms = $this->updateDataSetId($reportView->getTransforms());
        $reportView->setTransforms($transforms);

        $fieldTypes = $this->updateFieldTypesForReportView($reportView);
        $reportView->setFieldTypes($fieldTypes);

        $joinBy = $this->updateJoinByForReportView($reportView->getJoinBy());
        $reportView->setJoinBy($joinBy);

        $alias = $reportView->getAlias();
        if (empty($alias)) {
            $reportView->setAlias($reportView->getName());
        }
    }

    /**
     * @param ReportViewDataSetInterface $reportViewDataSet
     */
    private function correctFieldsInReportViewDataSet(ReportViewDataSetInterface $reportViewDataSet)
    {
        $dimensions = $reportViewDataSet->getDimensions();
        $metrics = $reportViewDataSet->getMetrics();

        $filters = $this->updateDataSetId($reportViewDataSet->getFilters());
        $reportViewDataSet->setFilters($filters);

        $reportViewDataSet->setDimensions(array_values($dimensions));
        $reportViewDataSet->setMetrics(array_values($metrics));
    }

    /**
     * @param ReportViewMultiViewInterface $reportViewMultiView
     */
    private function correctFieldsInReportViewMultiView(ReportViewMultiViewInterface $reportViewMultiView)
    {
        $filters = $this->updateDataSetId($reportViewMultiView->getFilters());
        $reportViewMultiView->setFilters($filters);

        $dimensions = $this->updateDataSetId($reportViewMultiView->getDimensions());
        $reportViewMultiView->setDimensions($dimensions);

        $metrics = $this->updateDataSetId($reportViewMultiView->getMetrics());
        $reportViewMultiView->setMetrics($metrics);
    }

    /**
     * @param ReportViewInterface $reportView
     * @return array
     */
    protected function updateFieldTypesForReportView(ReportViewInterface $reportView)
    {
        $fieldTypes = [];

        if ($reportView->isMultiView()) {
            /** Get fieldTypes from sub views */
            $multiViews = $reportView->getReportViewMultiViews();
            if ($multiViews instanceof Collection) {
                $multiViews = $multiViews->toArray();
            }

            foreach ($multiViews as $multiView) {
                if (!$multiView instanceof ReportViewMultiViewInterface) {
                    continue;
                }
                $subView = $multiView->getSubView();
                $fieldTypes = array_merge($fieldTypes, $subView->getFieldTypes());
            }
        } else {
            /** Get fieldTypes from data sets */
            $reportViewDataSets = $reportView->getReportViewDataSets();
            if ($reportViewDataSets instanceof Collection) {
                $reportViewDataSets = $reportViewDataSets->toArray();
            }
            foreach ($reportViewDataSets as $reportViewDataSet) {
                if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                    continue;
                }

                $dataSet = $reportViewDataSet->getDataSet();

                $subFieldTypes = $dataSet->getAllDimensionMetrics();
                foreach ($subFieldTypes as $field => $type) {
                    $subFieldTypes[sprintf('%s_%s', $field, $dataSet->getId())] = $type;
                    unset($subFieldTypes[$field]);
                }
                $fieldTypes = array_merge($fieldTypes, $subFieldTypes);
            }
        }

        return $fieldTypes;
    }

    /**
     * @return mixed
     */
    protected function getMetricsKey()
    {
        return ReportViewMultiViewChangeListener::METRICS_KEY;
    }

    protected function getDimensionsKey()
    {
        return ReportViewMultiViewChangeListener::DIMENSIONS_KEY;
    }

    /**
     * @param array $dimensions
     * @return array
     */
    private function updateDataSetId(array $dimensions)
    {
        foreach ($dimensions as $key => &$dimension) {
            if (is_array($dimension)) {
                $dimension = $this->updateDataSetId($dimension);
            } else {
                foreach ($this->replaceDataSetId as $oldId => $newId) {
                    $pos = strpos($dimension, "_" . $oldId);
                    if ($pos != false) {
                        $dimension = str_replace("_" . $oldId, "_" . $newId, $dimension);
                        continue;
                    }
                }
            }
        }

        return $dimensions;
    }

    /**
     * @param $joinConfigs
     */
    private function updateJoinByForReportView($joinConfigs)
    {
        if (empty($joinConfigs) || !is_array($joinConfigs)) {
            return $joinConfigs;
        }

        foreach ($joinConfigs as &$config) {
            $joinFields = $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            foreach ($joinFields as &$joinField) {
                foreach ($this->replaceDataSetId as $oldId => &$newId) {
                    if ($joinField[SqlBuilder::JOIN_CONFIG_DATA_SET] == $oldId) {
                        $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET] = $newId;
                    }
                }
            }
            $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] = $joinFields;
        }

        return $joinConfigs;
    }

    /**
     * @param ReportViewInterface $reportView
     */
    private function setReportViewForReportViewDataSets(ReportViewInterface $reportView)
    {
        $rpDataSets = $reportView->getReportViewDataSets();
        if ($rpDataSets instanceof Collection) {
            $rpDataSets = $rpDataSets->toArray();
        }
        foreach ($rpDataSets as $rpDataSet) {
            /** @var ReportViewDataSetInterface $rpDataSet */
            $rpDataSet->setReportView($reportView);
            $this->em->merge($rpDataSet);
        }
        $reportView->setReportViewDataSets($rpDataSets);
        $this->reportViewManager->save($reportView);
    }

    /**
     * @param ReportViewInterface $reportView
     */
    private function setReportViewForReportViewMultiViews(ReportViewInterface $reportView)
    {
        $multiViews = $reportView->getReportViewMultiViews();
        if ($multiViews instanceof Collection) {
            $multiViews = $multiViews->toArray();
        }
        foreach ($multiViews as $multiView) {
            $multiView->setReportView($reportView);
            $this->em->merge($multiView);
        }
        $reportView->setReportViewMultiViews($multiViews);
        $this->reportViewManager->save($reportView);
    }

    /**
     * @param ReportViewInterface $reportView
     * @param PublisherInterface $publisher
     * @return ReportViewInterface
     */
    public function cloneReportView(ReportViewInterface $reportView, PublisherInterface $publisher)
    {
        $cloneReportView = clone $reportView;
        $cloneReportView->setId(null);
        $cloneReportView->setPublisher($publisher);

        $this->reportViewManager->save($cloneReportView);

        if (!$reportView->isMultiView()) {
            $reportViewDataSets = $reportView->getReportViewDataSets();
            if ($reportViewDataSets instanceof Collection) {
                $reportViewDataSets = $reportViewDataSets->toArray();
            }
            $cloneReportViewDataSets = [];

            foreach ($reportViewDataSets as &$reportViewDataSet) {
                if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                    continue;
                }

                $cloneReportViewDataSets[] = $this->cloneReportViewDataSet($reportViewDataSet, $cloneReportView, $publisher);
            }
            $cloneReportView->setReportViewDataSets($cloneReportViewDataSets);
        } else {
            $reportViewMultiViews = $reportView->getReportViewMultiViews();
            if ($reportViewMultiViews instanceof Collection) {
                $reportViewMultiViews = $reportViewMultiViews->toArray();
            }

            $cloneReportViewMultiViews = [];

            foreach ($reportViewMultiViews as $reportViewMultiView) {
                if (!$reportViewMultiView instanceof ReportViewMultiViewInterface) {
                    continue;
                }

                $cloneReportViewMultiViews[] = $this->cloneReportViewMultiView($reportViewMultiView, $cloneReportView, $publisher);
            }

            $cloneReportView->setReportViewMultiViews($cloneReportViewMultiViews);
        }

        $this->correctFieldsInReportView($cloneReportView);
        $this->reportViewManager->save($cloneReportView);

        return $cloneReportView;
    }

    /**
     * @param ReportViewDataSetInterface $reportViewDataSet
     * @param ReportViewInterface $cloneReportView
     * @param PublisherInterface $publisher
     * @return ReportViewDataSetInterface
     */
    private function cloneReportViewDataSet(ReportViewDataSetInterface $reportViewDataSet, ReportViewInterface $cloneReportView, PublisherInterface $publisher)
    {
        $cloneReportViewDataSet = clone $reportViewDataSet;
        $cloneReportViewDataSet->setId(null);
        $cloneReportViewDataSet->setReportView($cloneReportView);
        $cloneReportViewDataSet->setDataSet(null);
        $this->reportViewDataSetManager->save($cloneReportViewDataSet);

        $dataSet = $reportViewDataSet->getDataSet();
        $cloneReportViewDataSet->setDataSet($this->cloneDataSet($dataSet, $publisher));

        return $cloneReportViewDataSet;
    }

    /**
     * @param ReportViewMultiViewInterface $reportViewMultiView
     * @param ReportViewInterface $cloneReportView
     * @param PublisherInterface $publisher
     * @return ReportViewMultiViewInterface
     */
    private function cloneReportViewMultiView(ReportViewMultiViewInterface $reportViewMultiView, ReportViewInterface $cloneReportView, PublisherInterface $publisher)
    {
        $cloneReportViewMultiView = clone $reportViewMultiView;
        $cloneReportViewMultiView->setId(null);
        $cloneReportViewMultiView->setReportView($cloneReportView);
        $cloneReportViewMultiView->setSubView($this->cloneReportView($reportViewMultiView->getSubView(), $publisher));
        $this->reportViewMultiViewManager->save($cloneReportViewMultiView);

        return $cloneReportViewMultiView;
    }

    /**
     * @param DataSetInterface $dataSet
     * @param PublisherInterface $publisher
     * @return DataSetInterface
     */
    private function cloneDataSet(DataSetInterface $dataSet, PublisherInterface $publisher)
    {
        $cloneDataSet = clone $dataSet;
        $cloneDataSet->setTotalRow(0);
        $cloneDataSet->setId(null);
        $cloneDataSet->setPublisher($publisher);
        $this->dataSetManager->save($cloneDataSet);

        $this->replaceDataSetId[$dataSet->getId()] = $cloneDataSet->getId();

        return $cloneDataSet;
    }
}