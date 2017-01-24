<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Domain\DTO\Report\Filters\AbstractFilter;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$diManager = $container->get('ur.domain_manager.data_source_integration');
$xxx = $diManager->getIntegrationBySchedule(null);
$abc = 0;
//$sqlBuilder = $container->get('ur.services.report.sql_builder');
////$filter = new \UR\Domain\DTO\Report\Filters\DateFilter('date', AbstractFilter::TYPE_DATE, 'Y-m-d', ['2015-07-01', '2015-07-27']);
//$filter = ['field' => 'date', 'fieldType' => 'date', 'dateFormat' => 'Y-m-d', 'dateRange' => ['2015-07-01', '2015-07-27']];
//$dataSet = new \UR\Domain\DTO\Report\DataSets\DataSet(4, ['date'], [$filter], ['cpm', 'impressions', 'revenue', 'clicks']);