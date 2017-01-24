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
$xxx= strtotime('02/12/1989');

$today = date("F d, Y", $xxx);

$date = \DateTime::createFromFormat('Y ,M d', '2016 ,Apr 26');
echo $date->format('d/m/Y');


