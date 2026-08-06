<?php


use Dotenv\Dotenv;
use NovaSysCore\Config;


require __DIR__ . '/../vendor/autoload.php';



$dotenv = Dotenv::createImmutable(
    dirname(__DIR__)
);


$dotenv->load();



Config::load(
    'database',
    dirname(__DIR__) . '/config/database.php'
);