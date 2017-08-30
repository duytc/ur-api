<?php

namespace UR\Domain\DTO\Report\JoinBy;

class JoinConfig implements JoinConfigInterface
{
    /**
     * @var JoinFieldInterface[]
     */
    protected $joinFields;

    /**
     * @var string
     */
    protected $outputField;

    /**
     * @var array
     */
    protected $dataSets;

    /**
     * @var bool
     */
    protected $visible;

    /**
     * @var bool
     */
    protected $multiple;


    /**
     * @return JoinFieldInterface[]
     */
    public function getJoinFields()
    {
        return $this->joinFields;
    }

    /**
     * @param JoinFieldInterface[] $joinFields
     * @return self
     */
    public function setJoinFields($joinFields)
    {
        $this->joinFields = $joinFields;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputField()
    {
        return $this->outputField;
    }

    /**
     * @param string $outputField
     * @return self
     */
    public function setOutputField($outputField)
    {
        $this->outputField = $outputField;
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
     * @return self
     */
    public function setDataSets()
    {
        $this->dataSets = [];

        if (!is_array($this->joinFields))
        {
            return $this;
        }

        foreach ($this->joinFields as $joinField) {
            if (!$joinField instanceof JoinFieldInterface) {
                continue;
            }

            $this->dataSets[] = $joinField->getDataSet();
        }

        return $this;
    }

    /**
     * @param JoinFieldInterface $joinField
     * @return $this
     */
    public function addJoinField(JoinFieldInterface $joinField)
    {
        if (!is_array($this->joinFields)) {
            $this->joinFields = [];
        }

        $this->joinFields[] = $joinField;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @param boolean $visible
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
    }

    /**
     * @return boolean
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @param boolean $multiple
     */
    public function setMultiple(bool $multiple)
    {
        $this->multiple = $multiple;
    }
}