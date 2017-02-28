<?php

namespace UR\Bundle\ApiBundle\Event\CustomCodeParse;


use UR\Bundle\ApiBundle\Event\UrEvent;
use UR\Service\DTO\Collection;

class PreTransformColumnDataEvent extends UrEvent
{
    protected $fileName;
    protected $fileReference;

    /**@var Collection $collection */
    protected $collection;
    protected $priorModification;
    protected $dataAfterModification;

    /**
     * CustomCodeEvent constructor.
     * @param $publisherId
     * @param $connectedDataSourceId
     * @param $dataSourceId
     * @param $fileName
     * @param $fileReference
     * @param $collection
     * @param $priorModification
     * @param $dataAfterModification
     */
    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, &$collection, $priorModification, $dataAfterModification)
    {
        parent::__construct($publisherId, $connectedDataSourceId, $dataSourceId);
        $this->fileName = $fileName;
        $this->fileReference = $fileReference;
        $this->collection = $collection;
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
     * @return Collection
     */
    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * @param Collection $collection
     */
    public function setCollection(Collection $collection)
    {
        $this->collection = $collection;
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