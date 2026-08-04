<?php

namespace NovaSysCore;

class Application
{
    protected Container $container;


    public function __construct()
    {
        $this->container = new Container();

        $this->container->bind(
            "router",
            function(){
                return new Router();
            }
        );
    }


    public function container(): Container
    {
        return $this->container;
    }


    public function start(): string
    {
        $router = $this->container->make("router");


        $router->get("/", function(){

            echo "Sistema: " . Config::get("app.name") . " 🚀";

        });


        ob_start();

        $router->dispatch(
            "/",
            "GET"
        );


        return ob_get_clean();
    }
}