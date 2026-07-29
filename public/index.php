<?php

require_once "../vendor/autoload.php";

use NovaSysCore\Application;

$app = new Application();

echo $app->start();