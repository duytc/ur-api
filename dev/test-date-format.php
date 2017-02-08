<?php


namespace tagcade\dev;

$dates = array(
    'd-m-y' => '22-01-16',
    'd/m/y' => '23/01/16',
    'm-d-y' => '01-15-99',
    'm/d/y' => '01/16/99',
    'y-m-d'=> '16-01-25',
    'y/m/d'=> '16/01/26'
);

foreach ($dates as $format => $value){
    $date = \DateTime::createFromFormat($format, $value);
    echo $date->format('Y-m-d H:i:s') . "\n";
}