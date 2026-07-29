<?php

namespace NovaSysCore;

class Container
{
    protected array $services = [];


    public function bind(string $name, callable $resolver): void
    {
        $this->services[$name] = $resolver;
    }


    public function make(string $name)
    {
        if (!isset($this->services[$name])) {
            throw new \Exception("Servicio no registrado: {$name}");
        }

        return call_user_func($this->services[$name]);
    }
}