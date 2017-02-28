<?php


namespace tagcade\dev;

use AppKernel;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Entity\Core\DataSource;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();
$dataSource = new DataSource();

$connectedDataSource = new ConnectedDataSource();
$connectedDataSource->setReplayData(true);

/** @var EntityManager $entityManager */
$entityManager = $container->get('doctrine')->getManager();
$entityManager->persist($connectedDataSource);
$entityManager->flush();

var_dump("done");