<?php


namespace tagcade\dev;

use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\DataSet;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

/*
 * CODE BEGIN FROM HERE...
 */
//$AutoCreateDataImportWorker = $container->get('ur.service.AutoCreateDataImportWorker');
//$AutoCreateDataImportWorker->doFunctionImport..();


$testWorkerManager = $container->get('ur.worker.manager');


$code = 100;
$publisherId =2;
$params = array(
    'dataSourceName'=>'testData',
    'dataEntryName'=>'entryName',
    'formatEntryName'=>'formatEntryName'
);


$testWorkerManager->processAlert($code, $publisherId, $params);

