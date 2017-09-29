<?php

namespace tagcade\dev;


use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostLoadDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostParseDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreFilterDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformCollectionDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformColumnDataEvent;
use UR\Service\DTO\Collection;
use UR\Service\Parser\ParserInterface;
use UR\Service\Parser\UREventDispatcherInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';
$kernel = new AppKernel('dev', true);
$kernel->boot();


/** @var ContainerInterface $container */
$container = $kernel->getContainer();
/** @var EventDispatcherInterface $eventDispatcher */
$eventDispatcher = $container->get('event_dispatcher');

$connectedDataSourceId = 'connect datasource id';
$publisherId = 'publisher id';
$dataSourceId = 1;
$rows = ['date' => '2017-03-21', 'tag' => 'tag 200x300', 'request' => 12, 'revenue' => 1.3];
$columns = ['date', 'tag', 'request', 'revenue'];

// load
$postLoadEvent = new PostLoadDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $rows);
$eventDispatcher->dispatch(
    UREventDispatcherInterface::EVENT_NAME_POST_LOADED_DATA,
    $postLoadEvent
);
echo 'dispatched post load' . "\n";
$rows = $postLoadEvent->getRows();
var_dump($rows);

// filter
$preFilterEvent = new PreFilterDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $rows);
$eventDispatcher->dispatch(
    UREventDispatcherInterface::EVENT_NAME_PRE_FILTER_DATA,
    $preFilterEvent
);
echo 'dispatched pre filter' . "\n";
$rows = $preFilterEvent->getRows();
var_dump($rows);

// create collection for other events that use collection
$collection = new Collection($columns, $rows);

// pre transform collection
$preTransformCollectionEvent = new PreTransformCollectionDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $collection, $metadata = []);
$eventDispatcher->dispatch(
    UREventDispatcherInterface::EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA,
    $preTransformCollectionEvent
);
echo 'dispatched pre transform collection' . "\n";
$collection = $preTransformCollectionEvent->getCollection();
var_dump($collection);

// pre transform column
$preTransformColumnEvent = new PreTransformColumnDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $collection, $metadata = []);
$eventDispatcher->dispatch(
    UREventDispatcherInterface::EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA,
    $preTransformColumnEvent
);
echo 'dispatched pre transform column' . "\n";
$collection = $preTransformColumnEvent->getCollection();
var_dump($collection);

// post parse data
$postParseDataEvent = new PostParseDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $collection, $metadata = []);
$eventDispatcher->dispatch(
    UREventDispatcherInterface::EVENT_NAME_POST_PARSE_DATA,
    $postParseDataEvent
);
echo 'dispatched post parse data' . "\n";
$collection = $postParseDataEvent->getCollection();
var_dump($collection);