<?php


$currentDateTime = new DateTime('UTC');

$alertDateTime= DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d'). '15' .':' . '16');

$alertDateTime->setTimezone(new DateTimeZone('UTC'));
$alertDateTime->add(new DateInterval('P1D'));

echo phpinfo();