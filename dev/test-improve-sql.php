<?php


namespace tagcade\dev;

use AppKernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();
$uploadFileDir = $container->getParameter('upload_file_dir');

$sql = file_get_contents($uploadFileDir . "/../../dev/sample.sql");
$sql = str_replace("\n", " ", $sql);
$sql = "(" . $sql . ") as sub4";

$qb = $connection->createQueryBuilder();
$qb->select('*');
$qb->from($sql);

$start = microtime(true);
$abc = $qb->execute()->fetchAll();
$end = microtime(true);

echo ($end - $start);
