<?php


namespace UR\Service\Alert;


class AlertParams
{
    protected $action;
    protected $alertIds;
    protected $publisherId;
    protected $alertSource;
    protected $sourceId;

    /**
     * AlertParams constructor.
     * @param $action
     * @param $alertIds
     * @param $publisherId
     * @param $alertSource
     * @param $sourceId
     * @param $types
     */
    public function __construct($action, $alertIds, $publisherId, $alertSource, $sourceId, $types)
    {
        $this->action = $action;
        $this->alertIds = $alertIds;
        $this->publisherId = $publisherId;
        $this->alertSource = $alertSource;
        $this->sourceId = $sourceId;
        $this->types = $types;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param mixed $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }
    protected $types;
    /**
     * @return mixed
     */
    public function getAlertIds()
    {
        return $this->alertIds;
    }

    /**
     * @param mixed $alertIds
     */
    public function setAlertIds($alertIds)
    {
        $this->alertIds = $alertIds;
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
    public function getAlertSource()
    {
        return $this->alertSource;
    }

    /**
     * @param mixed $alertSource
     */
    public function setAlertSource($alertSource)
    {
        $this->alertSource = $alertSource;
    }

    /**
     * @return mixed
     */
    public function getSourceId()
    {
        return $this->sourceId;
    }

    /**
     * @param mixed $sourceId
     */
    public function setSourceId($sourceId)
    {
        $this->sourceId = $sourceId;
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param mixed $types
     */
    public function setTypes($types)
    {
        $this->types = $types;
    }
}