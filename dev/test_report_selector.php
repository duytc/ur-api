<?php
use Symfony\Component\DependencyInjection\ContainerInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$filter = ['field' => 'date', 'type' => 'date', 'format' => 'Y-m-d', 'startDate' => '2016-06-01', 'endDate' => '2016-06-30'];
$dataSet1 = ['dataSet' => 4, 'dimensions' => ['date'], 'filters' => [$filter], 'metrics' => ['cpm', 'impressions', 'revenue', 'clicks']];
$dataSet2 = ['dataSet' => 6, 'dimensions' => ['date', 'tag_id', 'tag_name', 'tag_size'], 'filters' => [$filter], 'metrics' => ['cpm', 'impressions', 'revenue', 'requests', 'fill_rate']];

$paramsBuilder = $container->get('ur.services.report.params_builder');
$reportSelector = $container->get('ur.services.report.report_selector');
$reportGrouper = $container->get('ur.services.report.report_grouper');
$reportBuilder = $container->get('ur.services.report.report_builder');
$reportBuilder = new \UR\Service\Report\ReportBuilder($reportSelector, $reportGrouper);

$params = array (
    'dataSets' => '[
      {
         "filters":[
            {
               "field":"date",
               "type":"date",
               "format":"Y-m-d",
               "startDate":"2016-06-01",
               "endDate":"2016-06-30"
            }
         ],
         "dimensions":[
            "date"
         ],
         "metrics":[
            "cpm",
            "revenue",
            "impressions", "clicks"
         ],
         "dataSet":4
      },
      {
         "filters":[
            {
               "field":"date",
               "type":"date",
               "format":"Y-m-d",
               "startDate":"2016-06-01",
               "endDate":"2016-06-30"
            }
         ],
         "dimensions":[
            "date", "tag_name", "tag_id", "tag_size"
         ],
         "metrics":[
            "impressions", "requests", "cpm", "revenue", "fill_rate"
         ],
         "dataSet":6
      }
   ]',
    'transform' => '[
      {
         "transformType":"all-fields",
         "fields":[
            "date"
         ],
         "field":null,
         "type":"groupBy"
      }
   ]',
    'joinBy' => 'date'
);

$params = $paramsBuilder->buildFromArray($params);
$reports = $reportBuilder->getReport($params);
var_dump($reports);
