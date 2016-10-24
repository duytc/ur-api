<?php

namespace UR\Worker\Workers;

use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;

class AutoCreateDataImportWorker
{
    /**
     * @var DataSetManagerInterface
     */
    private $em;

    function __construct(DataSetManagerInterface $em)
    {
        $this->em = $em;
    }

    function autoCreateDataImport($dataSetId)
    {
        // get all info of job..
        /**@var DataSetInterface $dataSet*/
        $dataSet = $this->em->find($dataSetId);
        $connectedDataSources = $dataSet->getConnectedDataSources();


        // create importHistory: createdTime


        // parse: Giang


        // filter


        // transform


        // import??


        // create dataImport: dynamic table


        // update importHistory: finishedTime
    }
}