<?php

namespace NovaSysCore;

use Dotenv\Dotenv;

class Kernel
{
    protected Application $application;


    public function __construct()
    {
        $this->loadEnvironment();

        $this->loadConfiguration();

        ErrorHandler::register();

        $this->application = new Application();
    }


    protected function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable(
            dirname(__DIR__)
        );

        $dotenv->load();
    }


    protected function loadConfiguration(): void
    {
        Config::load(
            'app',
            dirname(__DIR__) . '/config/app.php'
        );

        Config::load(
            'database',
            dirname(__DIR__) . '/config/database.php'
        );

        Config::load(
            'security',
            dirname(__DIR__) . '/config/security.php'
        );
    }


    public function run(): void
    {
        echo $this->application->start();
    }
}