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
     * @var DataSetInterface[]
     */
    protected $dataSets;

    /**
     * @var array
     */
    protected $joinedFields;

    /**
     * @var array
     */
    protected $filters;

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
     * @return DataSetInterface[]
     */
    public function getDataSets()
    {
        return $this->dataSets;
    }

    /**
     * @param DataSetInterface[] $dataSets
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
    public function getJoinedFields()
    {
        return $this->joinedFields;
    }

    /**
     * @param array $joinedFields
     * @return self
     */
    public function setJoinedFields($joinedFields)
    {
        $this->joinedFields = $joinedFields;
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