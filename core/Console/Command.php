<?php

namespace NovaSysCore\Console;

abstract class Command
{
    protected string $name = '';

    protected string $description = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    abstract public function execute(array $arguments = []): int;
}