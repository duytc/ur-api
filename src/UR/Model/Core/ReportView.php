<?php


namespace UR\Model\Core;


class ReportView implements ReportViewInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $dataSets;

    /**
     * @var array
     */
    protected $joinBy;

    /**
     * @var array
     */
    protected $transforms;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return array
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }

    /**
     * @param array $dataSets
     * @return self
     */
    public function setDataSets($dataSets)
    {
        $this->dataSets = $dataSets;
        return $this;
    }

    /**
     * @return array
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @param array $joinBy
     * @return self
     */
    public function setJoinBy($joinBy)
    {
        $this->joinBy = $joinBy;
        return $this;
    }

    /**
     * @return array
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @param array $transforms
     * @return self
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
        return $this;
    }
}