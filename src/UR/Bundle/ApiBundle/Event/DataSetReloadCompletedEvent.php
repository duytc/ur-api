<?php


namespace UR\Bundle\ApiBundle\Event;
use Symfony\Component\EventDispatcher\Event;

class DataSetReloadCompletedEvent extends Event
{
    const EVENT_NAME = 'ur.event.data_set_reload_completed';

    protected $dataSetId;

    /**
     * DataSetReloadCompletedEvent constructor.
     * @param $dataSetId
     */
    public function __construct($dataSetId)
    {
        $this->dataSetId = $dataSetId;
    }

    /**
     * @return mixed
     */
    public function getDataSetId()
    {
        return $this->dataSetId;
    }
}