<?php
namespace UR\Bundle\ApiBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class UrEvent extends Event
{
    protected $publisherId;
    protected $connectedDataSourceId;
    protected $dataSourceId;

    /**
     * UrEvent constructor.
     * @param $publisherId
     * @param $connectedDataSourceId
     * @param $dataSourceId
     */
    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId)
    {
        $this->publisherId = $publisherId;
        $this->connectedDataSourceId = $connectedDataSourceId;
        $this->dataSourceId = $dataSourceId;
    }

    /**
     * @return mixed
     */
    public function getPublisherId()
    {
        return $this->publisherId;
    }

    /**
     * @param mixed $publisherId
     */
    public function setPublisherId($publisherId)
    {
        $this->publisherId = $publisherId;
    }

    /**
     * @return mixed
     */
    public function getConnectedDataSourceId()
    {
        return $this->connectedDataSourceId;
    }

    /**
     * @param mixed $connectedDataSourceId
     */
    public function setConnectedDataSourceId($connectedDataSourceId)
    {
        $this->connectedDataSourceId = $connectedDataSourceId;
    }

    /**
     * @return mixed
     */
    public function getDataSourceId()
    {
        return $this->dataSourceId;
    }

    /**
     * @param mixed $dataSourceId
     */
    public function setDataSourceId($dataSourceId)
    {
        $this->dataSourceId = $dataSourceId;
    }
}