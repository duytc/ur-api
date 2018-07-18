<?php


namespace UR\Bundle\ApiBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class DataSetReloadCompletedEvent extends Event
{
    const EVENT_NAME = 'ur.event.data_set_reload_completed';

    protected $dataSetId;
    protected $isFromParseChunkFile;

    /**
     * DataSetReloadCompletedEvent constructor.
     * @param $dataSetId
     * @param bool $isFromParseChunkFile
     */
    public function __construct($dataSetId, $isFromParseChunkFile = false)
    {
        $this->dataSetId = $dataSetId;
        $this->isFromParseChunkFile = $isFromParseChunkFile;
    }

    /**
     * @return mixed
     */
    public function getDataSetId()
    {
        return $this->dataSetId;
    }

    /**
     * @return boolean
     */
    public function isIsFromParseChunkFile()
    {
        return $this->isFromParseChunkFile;
    }
}