<?php

require_once __DIR__.'/../vendor/autoload.php';

use \mre\Process;

$process = new Process('cat');
$process->send('hello');
$process->send('world');

foreach ($process->receive() as $s)
{
    echo $s . PHP_EOL;
}