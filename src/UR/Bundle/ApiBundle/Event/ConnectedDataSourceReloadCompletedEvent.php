<?php


namespace UR\Bundle\ApiBundle\Event;
use Symfony\Component\EventDispatcher\Event;

class ConnectedDataSourceReloadCompletedEvent extends Event
{
    const EVENT_NAME = 'ur.event.connected_data_source_reload_completed';

    protected $connectedDataSourceId;

    /**
     * ConnectedDataSourceReloadCompletedEvent constructor.
     * @param $connectedDataSourceId
     */
    public function __construct($connectedDataSourceId)
    {
        $this->connectedDataSourceId = $connectedDataSourceId;
    }

    /**
     * @return mixed
     */
    public function getConnectedDataSourceId()
    {
        return $this->connectedDataSourceId;
    }
}