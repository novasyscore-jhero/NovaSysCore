<?php

namespace NovaSysCore;

class Application
{
    protected Container $container;


    public function __construct()
    {
        $this->container = new Container();
    }


    public function container(): Container
    {
        return $this->container;
    }


    public function start(): string
    {
        return "NovaSysCore iniciado correctamente 🚀";
    }
}