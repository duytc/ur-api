<?php


namespace tagcade\dev;

use AppKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\DataSet;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

/*
 * CODE BEGIN FROM HERE...
 */

$date = \DateTime::createFromFormat('j-M-Y', '31-Mar-2009');
echo $date->format('d/m/Y');


