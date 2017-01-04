<?php


namespace tagcade\dev;

use AppKernel;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

$loader = require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('dev', true);
$kernel->boot();

/** @var ContainerInterface $container */
$container = $kernel->getContainer();

/*
 * CODE BEGIN FROM HERE...
 */
$dir = $container->getParameter('kernel.root_dir');
$result = shell_exec('~' . $dir . '/convert_to_utf8.sh ' . 'sample2-utf16_copy().csv');
$x = 1;