<?php

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

$csvFilePath = '/home/giangle/Downloads/video-report-2017-07-04-208_1499626926.csv';
$jsonFilePath = '/home/giangle/Downloads/video-report-2017-07-04-208_1499626926.json';

//read the whole CSV file to memory
$csv = new \UR\Service\DataSource\Csv($csvFilePath);
// open JSON file to write
$json = fopen($jsonFilePath, 'w');

$rows = $csv->getRows();
fputs($json, sprintf('{'.PHP_EOL.'"columns" : %s , '.PHP_EOL.'"rows": [', json_encode($csv->getColumns())));

foreach ($rows as $row) {
    fputs($json, PHP_EOL);
    fputs($json, json_encode($row));
    fputs($json, ',');
}

fputs($json, ']}');
fclose($json);

