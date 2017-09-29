<?php


namespace UR\Service\Parser;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostLoadDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostParseDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreFilterDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformCollectionDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformColumnDataEvent;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class UREventDispatcher implements UREventDispatcherInterface
{
    /** @var EventDispatcherInterface  */
    protected $eventDispatcher;

    /**
     * EventDispatcherInterface constructor.
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function postLoadDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        // dispatch event after loading data
        $postLoadDataEvent = new PostLoadDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection->getRows()
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_POST_LOADED_DATA,
            $postLoadDataEvent
        );

        return $collection;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preFilterDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        /* 2. do filtering data */
        // dispatch event pre filtering data
        $preFilterEvent = new PreFilterDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection->getRows()
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_FILTER_DATA,
            $preFilterEvent
        );

        return $collection;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preTransformCollectionDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        // dispatch event pre transforming collection data
        $preTransformCollectionEvent = new PreTransformCollectionDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA,
            $preTransformCollectionEvent
        );

        $collection = $preTransformCollectionEvent->getCollection();

        return $collection;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preTransformColumnDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        // dispatch event pre transforming column data
        $preTransformColumnEvent = new PreTransformColumnDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA,
            $preTransformColumnEvent
        );

        $collection = $preTransformColumnEvent->getCollection();

        return $collection;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function postParseDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        // dispatch event post parse data
        $postParseDataEvent = new PostParseDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_POST_PARSE_DATA,
            $postParseDataEvent
        );

        return $collection;
    }
}