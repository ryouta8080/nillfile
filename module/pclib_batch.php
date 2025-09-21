<?php
//ini_set('display_errors', "On");

require 'vendor/autoload.php';
require 'bot.php';

$dispatcher = new PCMVCDispatcher();
$dispatcher->setSystemRoot('../');
$dispatcher->setUpConfig();
$config = $dispatcher->loadConfig('../');

PCLib::setup( $config ,true);

$h = PCF::getHelper();


