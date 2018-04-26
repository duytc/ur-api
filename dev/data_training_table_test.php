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
$dir = $container->getParameter('kernel.root_dir');
$dataTrainingCollector = $container->get('ur.service.optimization_rule.data_training_collector');
$optimizationRuleManager = $container->get('ur.domain_manager.optimization_rule');

$optimizationRule = $optimizationRuleManager->find($optimizationRule = 6);

$data = $dataTrainingCollector->buildDataForOptimizationRule($optimizationRule);

foreach ($data->getRows() as $row) {

    $row;

}




