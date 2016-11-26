<?php
// exit successfully after this time, supervisord will then restart
// this is to prevent any memory leaks from running PHP for a long time
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
$entityManager = $container->get('doctrine.orm.entity_manager');
$queue = $container->get("leezy.pheanstalk");
// only tasks listed here are able to run
$availableWorkers = [
    $container->get('ur.worker.workers.re_import_new_entry_received'),
    $container->get('ur.worker.workers.alert_worker'),
    $container->get('ur.worker.workers.synchronize_user_worker')
];

$workerPool = new \UR\Worker\Pool($availableWorkers);

function stdErr($text)
{
    file_put_contents('php://stderr', trim($text) . "\n", FILE_APPEND);
}

function stdOut($text)
{
    file_put_contents('php://stdout', trim($text) . "\n", FILE_APPEND);
}

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
        stdErr(sprintf('Received an invalid payload %s', $rawPayload));
        $queue->bury($job);
        continue;
    }
    $task = $payload->task;
    $params = $payload->params;
    $worker = $workerPool->findWorker($task);
    if (!$worker) {
        stdErr(sprintf('The task "%s" is unknown', $task));
        $queue->bury($job);
        continue;
    }
    if (!$params instanceof Stdclass) {
        stdErr(sprintf('The task parameters are not valid', $task));
        $queue->bury($job);
        continue;
    }
    stdOut(sprintf('Received job %s (ID: %s) with payload %s', $task, $job->getId(), $rawPayload));
    try {
        $worker->$task($params); // dynamic method call
        stdOut(sprintf('Job %s (ID: %s) with payload %s has been completed', $task, $job->getId(), $rawPayload));
        $queue->delete($job);
// task finished successfully
    } catch (Exception $e) {
        stdOut(
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
