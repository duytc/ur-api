<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ImportHistoryInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return mixed
     */
    public function getDescription();

    /**
     * @param mixed $description
     */
    public function setDescription($description);

    /**
     * @return ConnectedDataSourceInterface
     */
    public function getConnectedDataSource();

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     */
    public function setConnectedDataSource($connectedDataSource);
}