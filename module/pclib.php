<?php
//ini_set('display_errors', "On");
ini_set("display_errors", 0);
ini_set("display_startup_errors", 0);
error_reporting(E_ALL & ~E_STRICT );

require 'vendor/autoload.php';
require 'ptlib/src/loader.php';

$config = PCMVCDispatcher::loadConfig('../');

PCLib::setup($config);

$h = PCF::getHelper();

