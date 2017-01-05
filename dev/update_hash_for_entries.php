<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Domain\DTO\Report\Filters\AbstractFilter;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

$entryManager = $container->get('ur.domain_manager.data_source_entry');

$allEntries = $entryManager->all();
foreach ($allEntries as $entry) {
    /**@var \UR\Model\Core\DataSourceEntryInterface $entry */
    $hash = sha1_file($container->getParameter('upload_file_dir') . $entry->getPath());
    $entry->setHashFile($hash);
    $entryManager->save($entry);
}