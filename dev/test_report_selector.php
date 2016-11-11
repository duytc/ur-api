<?php
use Symfony\Component\DependencyInjection\ContainerInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$filter = ['field' => 'date', 'fieldType' => 'date', 'dateFormat' => 'Y-m-d', 'dateRange' => ['2016-06-01', '2016-06-30']];
$dataSet1 = ['dataSet' => 4, 'dimensions' => ['date'], 'filters' => [$filter], 'metrics' => ['cpm', 'impressions', 'revenue', 'clicks']];
$dataSet2 = ['dataSet' => 6, 'dimensions' => ['date', 'tag_id', 'tag_name', 'tag_size'], 'filters' => [$filter], 'metrics' => ['cpm', 'impressions', 'revenue', 'requests', 'fill_rate']];

$paramsBuilder = $container->get('ur.services.report.params_builder');
$reportSelector = $container->get('ur.services.report.report_selector');

$params = $paramsBuilder->buildFromArray(['dataSets' => [$dataSet1, $dataSet2], 'joinByFields' => 'date', 'transforms' => []]);
$stmt = $reportSelector->getReportData($params);
$reports = $stmt->fetchAll();
var_dump($reports);
