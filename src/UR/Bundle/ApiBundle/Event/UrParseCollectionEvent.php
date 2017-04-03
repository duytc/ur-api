<?php

namespace UR\Bundle\ApiBundle\Event;

use UR\Service\DTO\Collection;

class UrParseCollectionEvent extends UrEvent
{
    /**
     * @var Collection
     */
    protected $collection;
    /**
     * @var array
     */
    protected $metadata;

    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId, Collection $collection, array $metadata = [])
    {
        parent::__construct($publisherId, $connectedDataSourceId, $dataSourceId);
        $this->collection = $collection;
        $this->metadata = $metadata;
    }

    /**
     * @return Collection
     */
    public function getCollection(): Collection
    {
        return $this->collection;
    }

    /**
     * @param Collection $collection
     */
    public function setCollection(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}