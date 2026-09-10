<?php

namespace NovaSysCore\Context;

class CompanyContextStore
{
    private static ?CompanyContext $context =
        null;

    public static function set(
        CompanyContext $context
    ): void {
        self::$context = $context;
    }

    public static function get(): ?CompanyContext
    {
        return self::$context;
    }

    public static function has(): bool
    {
        return self::$context !== null;
    }

    public static function clear(): void
    {
        self::$context = null;
    }
}