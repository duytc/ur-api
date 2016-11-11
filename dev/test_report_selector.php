<?php
use Symfony\Component\DependencyInjection\ContainerInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$filter = ['field' => 'date', 'fieldType' => 'date', 'dateFormat' => 'Y-m-d', 'dateRange' => ['2015-07-01', '2015-07-27']];
$dataSet = ['dataSet' => 4, 'dimensions' => ['date'], 'filters' => [$filter], 'metrics' => ['cpm', 'impressions', 'revenue', 'clicks']];

$paramsBuilder = $container->get('ur.services.report.params_builder');
$reportSelector = $container->get('ur.services.report.report_selector');

$params = $paramsBuilder->buildFromArray(['dataSets' => [$dataSet], 'joinByFields' => 'date', 'transforms' => []]);
var_dump($params);