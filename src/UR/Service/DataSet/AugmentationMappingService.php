<?php


namespace UR\Service\DataSet;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Entity\Core\MapBuilderConfig;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;

class AugmentationMappingService
{
    /**
     * @param DataSetInterface $dataSet
     * @param EntityManagerInterface $em
     */
    public function noticeChangesInLeftRightMapBuilder(DataSetInterface $dataSet, EntityManagerInterface $em)
    {
        /** Get MapBuilders from deleted data set*/
        $mapBuilderRepository = $em->getRepository(MapBuilderConfig::class);
        $mapBuilderConfigs = $mapBuilderRepository->getByMapDataSet($dataSet);

        if (empty($mapBuilderConfigs)) {
            return;
        }

        if ($mapBuilderConfigs instanceof Collection) {
            $mapBuilderConfigs = $mapBuilderConfigs->toArray();
        }

        foreach ($mapBuilderConfigs as $mapBuilderConfig) {
            if (!$mapBuilderConfig instanceof MapBuilderConfigInterface) {
                continue;
            }

            $this->noticeChangesInMapBuilderConfig($mapBuilderConfig, $em);
        }
    }

    /**
     * @param MapBuilderConfigInterface $mapBuilderConfig
     * @param EntityManagerInterface $em
     */
    public function noticeChangesInMapBuilderConfig(MapBuilderConfigInterface $mapBuilderConfig, EntityManagerInterface $em)
    {
        $dataSet = $mapBuilderConfig->getDataSet();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $this->noticeChangesInDataSetMapBuilder($dataSet, $em);
    }

    /**
     * @param DataSetInterface $dataSet
     * @param EntityManagerInterface $em
     */
    public function noticeChangesInDataSetMapBuilder(DataSetInterface $dataSet, EntityManagerInterface $em)
    {
        /** Get LinkMapDataSets */
        $linkMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);

        $linkedMapDataSets = $linkMapDataSetRepository->getByMapDataSet($dataSet);

        if (empty($linkedMapDataSets)) {
            return;
        }

        if ($linkedMapDataSets instanceof Collection) {
            $linkedMapDataSets = $linkedMapDataSets->toArray();
        }

        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            if (!$linkedMapDataSet instanceof LinkedMapDataSetInterface) {
                continue;
            }

            $this->noticeChangesInLinkedMapDataSet($linkedMapDataSet, $em);
        }
    }

    /**
     * @param LinkedMapDataSetInterface $linkedMapDataSet
     * @param EntityManagerInterface $em
     */
    public function noticeChangesInLinkedMapDataSet(LinkedMapDataSetInterface $linkedMapDataSet, EntityManagerInterface $em)
    {
        $connectedDataSource = $linkedMapDataSet->getConnectedDataSource();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $this->noticeChangesInConnectedDataSource($connectedDataSource, $em);
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param EntityManagerInterface $em
     */
    public function noticeChangesInConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, EntityManagerInterface $em)
    {
        $connectedDataSource->increaseNumChanges();
        $em->persist($connectedDataSource);

        $dataSet = $connectedDataSource->getDataSet();

        if ($dataSet instanceof DataSetInterface) {
            $dataSet->increaseNumChanges();
            $em->persist($dataSet);
        }

        try {
            $em->flush();
        } catch (\Exception $e) {

        }
    }
}