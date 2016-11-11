<?php
$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

$report = [
    ['date' => '2016-11-10', 'impressions' => 12, 'clicks' => 24, 'name' => 'tag 1'],
    ['date' => '2016-11-10', 'impressions' => 12, 'clicks' => 24, 'name' => 'tag 2'],
    ['date' => '2016-11-10', 'impressions' => 13, 'clicks' => 25, 'name' => 'tag 1'],
    ['date' => '2016-11-10', 'impressions' => 14, 'clicks' => 26, 'name' => 'tag 1'],
    ['date' => '2016-11-10', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
    ['date' => '2016-11-09', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
    ['date' => '2016-11-09', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
    ['date' => '2016-11-09', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
    ['date' => '2016-11-09', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 2'],
    ['date' => '2016-11-09', 'impressions' => 5, 'clicks' => 7, 'name' => 'tag 2'],
    ['date' => '2016-11-08', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
    ['date' => '2016-11-08', 'impressions' => 15, 'clicks' => 27, 'name' => 'tag 1'],
];

$grouper = new \UR\Service\Report\Groupers\DefaultGrouper();
$result = $grouper->getGroupedReport(['date', 'name'], $report, ['impressions', 'clicks'], ['date', 'name']);
var_dump($result);
