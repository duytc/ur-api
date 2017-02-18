<?php

namespace UR\Service\Parser;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSource\DataSourceInterface;

interface ParserInterface
{
    const EVENT_NAME_POST_LOADED_DATA = 'ur.events.custom_code_event.post_loaded_data';
    const EVENT_NAME_PRE_FILTER_DATA = 'ur.events.custom_code_event.pre_filter_data';
    const EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA = 'ur.events.custom_code_event.pre_transform_collection_data';
    const EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA = 'ur.events.custom_code_event.pre_transform_column_data';

    public function parse(DataSourceInterface $dataSource, ParserConfig $parserConfig, ConnectedDataSourceInterface $dataSet);
}