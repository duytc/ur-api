<?php

namespace tagcade\dev;


use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostLoadDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreFilterDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformCollectionDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformColumnDataEvent;
use UR\Bundle\ApiBundle\Event\UrGenericEvent;
use UR\Service\DTO\Collection;
use UR\Service\Parser\ParserInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';
$kernel = new AppKernel('dev', true);
$kernel->boot();


/** @var ContainerInterface $container */
$container = $kernel->getContainer();
/** @var EventDispatcherInterface $eventDispatcher */
$eventDispatcher = $container->get('event_dispatcher');

$connectedDataSourceId = "connect datasource id";
$fileReference = "file reference";
$priorModification = "prior modification";
$fileName = "file name";
$dataAfterModification = "data after modification";
$publisherId = "publisher id";
$dataSourceId = 1;
$rows = ["row1", "row2", "row3"];
$columns = ["column1", "column2", "column3"];
$collection = new Collection($columns, $rows);

// load
$postLoadEvent = new UrGenericEvent(
    new PostLoadDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, $rows, $priorModification, $dataAfterModification)
);
$eventDispatcher->dispatch(
    ParserInterface::EVENT_NAME_POST_LOADED_DATA,
    $postLoadEvent
);
echo "dispatched post load\n";
var_dump($postLoadEvent->getArguments());

// filter
$preFilterEvent = new UrGenericEvent(
    new PreFilterDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, $rows, $priorModification, $dataAfterModification)
);
$eventDispatcher->dispatch(
    ParserInterface::EVENT_NAME_PRE_FILTER_DATA,
    $preFilterEvent
);
echo "dispatched pre filter\n";
var_dump($preFilterEvent->getArguments());

// pre transform collection
$preTransformCollectionEvent = new UrGenericEvent(
    new PreTransformCollectionDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, $collection, $priorModification, $dataAfterModification)
);
$eventDispatcher->dispatch(
    ParserInterface::EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA,
    $preTransformCollectionEvent
);
echo "dispatched pre transform collection\n";
var_dump($preTransformCollectionEvent->getArguments());

// pre transform column
$preTransformColumnEvent = new UrGenericEvent(
    new PreTransformColumnDataEvent($publisherId, $connectedDataSourceId, $dataSourceId, $fileName, $fileReference, $collection, $priorModification, $dataAfterModification)
);
$eventDispatcher->dispatch(
    ParserInterface::EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA,
    $preTransformColumnEvent
);
echo "dispatched pre transform column\n";
var_dump($preTransformColumnEvent->getArguments());