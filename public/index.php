<?php
//$time_start = microtime(true);

require_once '../module/pclib.php';

$dispatcher = new PCMVCDispatcher();
$dispatcher->setSystemRoot('../');
$dispatcher->dispatch();

//$time = microtime(true) - $time_start;
//echo "{$time} 秒";

