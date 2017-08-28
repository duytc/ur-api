<?php

namespace UR\Service\Parser;

use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;

interface ParserInterface
{
    const EVENT_NAME_POST_LOADED_DATA = 'ur.events.custom_code_event.post_loaded_data';
    const EVENT_NAME_PRE_FILTER_DATA = 'ur.events.custom_code_event.pre_filter_data';
    const EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA = 'ur.events.custom_code_event.pre_transform_collection_data';
    const EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA = 'ur.events.custom_code_event.pre_transform_column_data';
    const EVENT_NAME_POST_PARSE_DATA = 'ur.events.custom_code_event.post_parse_data';

    public function parse(array $fileCols, SplDoublyLinkedList $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $dataSet);
}