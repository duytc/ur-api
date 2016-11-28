<?php


namespace UR\Model\Core;


use UR\Model\User\Role\PublisherInterface;

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
     * @var string
     */
    protected $joinBy;

    /**
     * @var array
     */
    protected $transforms;

    /**
     * @var array
     */
    protected $weightedCalculations;

    /**
     * @var string
     */
    protected $sharedKey;

    /**
     * @var PublisherInterface
     */
    protected $publisher;

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
     * @return string
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @param string $joinBy
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

    /**
     * @return PublisherInterface
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getWeightedCalculations()
    {
        return $this->weightedCalculations;
    }

    /**
     * @inheritdoc
     */
    public function setWeightedCalculations($weightedCalculations)
    {
        $this->weightedCalculations = $weightedCalculations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSharedKey()
    {
        return $this->sharedKey;
    }

    /**
     * @inheritdoc
     */
    public function setSharedKey($sharedKey)
    {
        $this->sharedKey = $sharedKey;
        return $this;
    }

    /**
     * Use this if need generate shared key automatically
     * @return self
     */
    public static function generateSharedKey()
    {
        return uniqid(rand(1, 10000), true);
    }
}