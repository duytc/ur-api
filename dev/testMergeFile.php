<?php


namespace tagcade\dev;

use AppKernel;
use DateTime;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\DataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\DataSet\ParsedDataImporter;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\ReloadParams;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSource\MergeFiles;
use UR\Service\DTO\Collection;


$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$file1 = '/var/www/api.unified-reports.dev/data/Json/sample1.json';
$file1 = '/var/www/api.unified-reports.dev/data/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file2 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file3 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file4 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file5 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file6 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file7 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file8 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file9 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file10 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file11 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file12 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file13 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file14 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file15 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file16 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file17 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file18 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file19 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file20 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file21 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file22 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';
$file23 = '/var/www/api.unified-reports.dev/data/ExcelFile/TrendByPlacement_DTS-234-20171009-2017Nov26-small file datetime.xlsx';

$outputDirectory = '/var/www/api.unified-reports.dev/data';

$dataSourceFiles = [$file1, $file2,$file3, $file4,$file5, $file6,$file7,
    $file8, $file9, $file10,$file11, $file12,$file13, $file14, $file15,
    $file16, $file17, $file18, $file19, $file20, $file21, $file22, $file23];


$mergeFile =  new MergeFiles($dataSourceFiles, $outputDirectory);
$result =  $mergeFile->mergeFiles();















