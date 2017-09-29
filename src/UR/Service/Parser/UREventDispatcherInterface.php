<?php

namespace UR\Service\Parser;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

interface UREventDispatcherInterface
{
    const EVENT_NAME_POST_LOADED_DATA = 'ur.events.custom_code_event.post_loaded_data';
    const EVENT_NAME_PRE_FILTER_DATA = 'ur.events.custom_code_event.pre_filter_data';
    const EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA = 'ur.events.custom_code_event.pre_transform_collection_data';
    const EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA = 'ur.events.custom_code_event.pre_transform_column_data';
    const EVENT_NAME_POST_PARSE_DATA = 'ur.events.custom_code_event.post_parse_data';
    
    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function postLoadDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preFilterDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preTransformCollectionDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function preTransformColumnDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection);

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    public function postParseDataEvent(ConnectedDataSourceInterface $connectedDataSource, Collection $collection);
}