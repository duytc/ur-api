<?php

namespace UR\Service\DataSet;

use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\DataSetImportJob;
use UR\Model\Core\DataSetInterface;

class UpdateNumOfPendingLoad
{
    /**
     * @param DataSetInterface $dataSet
     * @param EntityManagerInterface $em
     * @return bool
     */
    public function updateNumberOfPendingLoadForDataSet($dataSet, $em)
    {
        if (!$dataSet instanceof DataSetInterface) {
            return false;
        }

        $dataSetImportJobRepository = $em->getRepository(DataSetImportJob::class);
        $numberOfJobs = $dataSetImportJobRepository->getAllExecuteImportJobsByDataSetId($dataSet->getId());
        if (!is_array($numberOfJobs)) {
            $numberOfJobs = [];
        }

        $dataSet->setNumOfPendingLoad(count($numberOfJobs));
        $em->persist($dataSet);
        $em->flush();
        return true;
    }
}