<?php
// exit successfully after this time, supervisord will then restart
// this is to prevent any memory leaks from running PHP for a long time
use Monolog\Handler\StreamHandler;
const WORKER_TIME_LIMIT = 10800; // 3 hours
const TUBE_NAME = 'ur-api-worker';
const RESERVE_TIMEOUT = 3600;
// Set the start time
$startTime = time();
$loader = require_once __DIR__ . '/../app/autoload.php';

require_once __DIR__ . '/../app/AppKernel.php';

$env = getenv('SYMFONY_ENV') ?: 'prod';
$debug = false;

if ($env == 'dev') {
    $debug = true;
}

$kernel = new AppKernel($env, $debug);
$kernel->boot();

/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer();

$logger = $container->get('logger');
$logger->pushHandler(new StreamHandler("php://stderr", \Monolog\Logger::DEBUG));

$entityManager = $container->get('doctrine.orm.entity_manager');
$queue = $container->get("leezy.pheanstalk");
// only tasks listed here are able to run
$availableWorkers = [
    $container->get('ur.worker.workers.re_import_new_entry_received'),
    $container->get('ur.worker.workers.alert_worker'),
    $container->get('ur.worker.workers.synchronize_user_worker'),
    $container->get('ur.worker.workers.alter_import_data_table'),
    $container->get('ur.worker.workers.truncate_import_data_table'),
    $container->get('ur.worker.workers.update_detected_fields_and_data_source_entry_total_row'),
    $container->get('ur.worker.workers.csv_fix_window_line_feed'),
    $container->get('ur.worker.workers.truncate_data_set'),
];

$workerPool = new \UR\Worker\Pool($availableWorkers);

while (true) {
    if (time() > ($startTime + WORKER_TIME_LIMIT)) {
// exit worker gracefully, supervisord will restart it
        break;
    }
    $job = $queue->watch(TUBE_NAME)
        ->ignore('default')
        ->reserve(RESERVE_TIMEOUT);
    if (!$job) {
        continue;
    }
    $worker = null; // important to reset the worker every loop
    $rawPayload = $job->getData();
    $payload = json_decode($rawPayload);
    if (!$payload) {
        $logger->error(sprintf('Received an invalid payload %s', $rawPayload));
        $queue->bury($job);
        continue;
    }
    $task = $payload->task;
    $params = $payload->params;
    $worker = $workerPool->findWorker($task);
    if (!$worker) {
        $logger->error(sprintf('The task "%s" is unknown', $task));
        $queue->bury($job);
        continue;
    }
    if (!$params instanceof stdclass) {
        $logger->error(sprintf('The task parameters are not valid', $task));
        $queue->bury($job);
        continue;
    }
    $logger->notice(sprintf('[%s] Received job %s (ID: %s) with payload %s', (new DateTime())->format('Y-m-d H:i:s'), $task, $job->getId(), $rawPayload));
    try {
        if ($task == 'loadingDataFromFileToDataImportTable' || $task == 'alterDataSetTable' || $task == 'truncateDataSetTable') {
            $worker->$task($params, $job, TUBE_NAME);
        } else {
            $worker->$task($params); // dynamic method call
        }

        $logger->notice(sprintf('Job %s (ID: %s) with payload %s has been completed', $task, $job->getId(), $rawPayload));
        $queue->delete($job);
// task finished successfully
    } catch (Exception $e) {
        $logger->warning(
            sprintf(
                'Job %s (ID: %s) with payload %s failed with an exception: %s',
                $task,
                $job->getId(),
                $rawPayload,
                $e->getMessage()
            )
        );
        $queue->bury($job);
    }
    $entityManager->clear();
    gc_collect_cycles();
}
