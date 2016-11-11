<?php


namespace tagcade\dev;

use AppKernel;
use stdClass;
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


$test = $container->get('ur.worker.workers.import_dataset_worker');
$path= $container->getParameter('upload_file_dir');
$param = new StdClass;
$param->dataSetId = 4;
$param->filePath = $path;
$test->autoCreateDataImport($param);