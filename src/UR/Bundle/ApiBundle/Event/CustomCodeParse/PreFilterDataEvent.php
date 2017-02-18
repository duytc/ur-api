<?php

namespace UR\Bundle\ApiBundle\Event\CustomCodeParse;


use UR\Bundle\ApiBundle\Event\UrEvent;

class PreFilterDataEvent extends UrEvent
{
    protected $fileName;
    protected $fileReference;
    protected $rows;
    protected $priorModification;
    protected $dataAfterModification;

    /**
     * CustomCodeEvent constructor.
     * @param $publisherId
     * @param $connectedDataSourceId
     * @param $dataSourceId
     * @param $fileName
     * @param $fileReference
     * @param $rows
     * @param $priorModification
     * @param $dataAfterModification
     */
    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, &$rows, $priorModification, $dataAfterModification)
    {
        parent::__construct($publisherId, $connectedDataSourceId, $dataSourceId);
        $this->fileName = $fileName;
        $this->fileReference = $fileReference;
        $this->rows = $rows;
        $this->priorModification = $priorModification;
        $this->dataAfterModification = $dataAfterModification;
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @param mixed $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return mixed
     */
    public function getFileReference()
    {
        return $this->fileReference;
    }

    /**
     * @param mixed $fileReference
     */
    public function setFileReference($fileReference)
    {
        $this->fileReference = $fileReference;
    }

    /**
     * @return mixed
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param $rows
     */
    public function setRows($rows)
    {
        $this->rows = $rows;
    }

    /**
     * @return mixed
     */
    public function getPriorModification()
    {
        return $this->priorModification;
    }

    /**
     * @param mixed $priorModification
     */
    public function setPriorModification($priorModification)
    {
        $this->priorModification = $priorModification;
    }

    /**
     * @return mixed
     */
    public function getDataAfterModification()
    {
        return $this->dataAfterModification;
    }

    /**
     * @param mixed $dataAfterModification
     */
    public function setDataAfterModification($dataAfterModification)
    {
        $this->dataAfterModification = $dataAfterModification;
    }
}