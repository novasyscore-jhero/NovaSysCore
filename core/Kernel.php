<?php

namespace NovaSysCore;

class Kernel
{
    protected Application $application;

    public function __construct()
    {
        $this->application = new Application();
    }


    public function run(): void
    {
        echo $this->application->start();
    }
}