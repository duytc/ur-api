<?php


namespace tagcade\dev;

use AppKernel;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\DataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DTO\Collection;
use UR\Service\Parser\ImportUtils;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$dataSetInputs = [
    ["name" => "dataSet_1", "dimensions" => ["date" => "date", "tagname" => "text"], "metrics" => ["request" => "number", "impressions" => "number"]],
    ["name" => "dataSet_2", "dimensions" => ["date2" => "date", "tagname" => "text"], "metrics" => ["request2" => "number", "impressions2" => "number"]]
];

$publisherId = 2;

$importId = 1;
$dataSourceId = 2;

$startDate = '2016-12-05';
$endDate = '2016-12-17';

$tagName = ['tagName1', 'tagName2', 'tagName3', 'tagName4'];

$publisherManager = $container->get('ur_user.domain_manager.publisher');
$publisher = $publisherManager->findPublisher($publisherId);
if (!$publisher instanceof PublisherInterface) {
    throw new \Exception(sprintf('Publisher Id = %d doest not exit in systems'));
}

// Create new DataSet for this publisher
$dataSetManager = $container->get('ur.domain_manager.data_set');

foreach ($dataSetInputs as $dataSetInput) {
    $exitsDataSet = $dataSetManager->getDataSetByName($dataSetInput['name']);
    if (!empty($exitsDataSet)) {
        continue;
    }

    $dataSet = new DataSet();
    $dataSet->setPublisher($publisher);
    $dataSet->setName($dataSetInput['name']);
    $dataSet->setMetrics($dataSetInput['metrics']);
    $dataSet->setDimensions(($dataSetInput['dimensions']));
    $dataSetManager->save($dataSet);
}
/** @var DataSetInterface[] $dataSets */
$dataSets = $dataSetManager->getDataSetForPublisher($publisher);

if (empty($dataSets)) {
    throw new \Exception (sprintf('There is no data set for publisher = $%d in this system', $publisherId));
}

const DATA_SET_TABLE_NAME_TEMPLATE = '__data_import_%d';

$em = $container->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();
$sm = $connection->getSchemaManager();
$dataSetLocator = new Locator($connection);
$dataSetSynchronizer = new Synchronizer($connection, new Comparator());

$importUtils = new ImportUtils();
foreach ($dataSets as $dataSet) {
    if (!$sm->tablesExist(sprintf(DATA_SET_TABLE_NAME_TEMPLATE,$dataSet->getId()))){
        $importUtils->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $connection);
    }
}

$dataImporter = new Importer($connection);
$startDateObject = date_create_from_format('Y-m-d', $startDate);
$endDateObject = date_create_from_format('Y-m-d', $endDate);

$numDays = ($endDateObject->diff($startDateObject)->days);

/** @var DataSetInterface[] $dataSets */
foreach ($dataSets as $dataSet) {
    $startDateObject = date_create_from_format('Y-m-d', $startDate);
    $tableName = sprintf(DATA_SET_TABLE_NAME_TEMPLATE, $dataSet->getId());
    $table = $sm->listTableDetails($tableName);
    $columns = $table->getColumns();

    $columnOfCollection = [];
    $rowsOfCollection = [];
    $types = [];
    $scaleDecimalColumns = [];

    foreach ($columns as $column) {
        if ($column->getName() == '__id' || $column->getName() == '__data_source_id' || $column->getName() == '__import_id') {
            continue;
        }

        $columnOfCollection[] = $column->getName();
        $types[$column->getName()] = $column->getType()->getName();
        if ($column->getType()->getName() == 'decimal') {
            $scaleDecimalColumns[$column->getName()] = $column->getScale();
        }
    }

    for ($i = 1; $i < $numDays; $i++) {
        $oneRow = [];
        foreach ($types as $columnName => $type) {
            switch ($type) {
                case 'date':
                    $oneRow[$columnName] = $startDateObject->modify('+1 day')->format('Y-m-d');
                    break;
                case 'text':
                    $oneRow[$columnName] = $tagName[array_rand($tagName)];
                    break;
                case 'decimal':
                    if ($scaleDecimalColumns[$columnName] == 0) {
                        $oneRow[$columnName] = mt_rand(100, 90000);
                    } else {
                        $oneRow[$columnName] = mt_rand(100, 90000) / 1000;
                    }
                    break;
                default:
                    break;
            }
        }
        $rowsOfCollection[] = $oneRow;
    }

    $collection = new Collection($columnOfCollection, $rowsOfCollection);
    $dataImporter->importCollection($collection, $table, $importId, $dataSourceId);
}


