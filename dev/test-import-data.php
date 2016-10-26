<?php


namespace tagcade\dev;

use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

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


$test = $container->get('ur.worker.workers.import_dataset_worker');
$test->autoCreateDataImport(19);