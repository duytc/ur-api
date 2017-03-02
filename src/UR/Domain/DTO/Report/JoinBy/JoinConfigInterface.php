<?php

namespace UR\Domain\DTO\Report\JoinBy;

interface JoinConfigInterface
{
    /**
     * @return JoinFieldInterface[]
     */
    public function getJoinFields();

    /**
     * @param JoinFieldInterface[] $joinFields
     * @return self
     */
    public function setJoinFields($joinFields);

    /**
     * @return string
     */
    public function getOutputField();

    /**
     * @param string $outputField
     * @return self
     */
    public function setOutputField($outputField);

    /**
     * @return array
     */
    public function getDataSets();

    /**
     * @return self
     */
    public function setDataSets();

    /**
     * @param JoinFieldInterface $joinField
     * @return $this
     */
    public function addJoinField(JoinFieldInterface $joinField);

    /**
     * @return boolean
     */
    public function isHiddenJoinColumn();

    /**
     * @param boolean $hidden
     */
    public function setHiddenJoinColumn($hidden);
}