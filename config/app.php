<?php

return [

    'name' => $_ENV['APP_NAME'] ?? 'NovaSysCore',

    'environment' => $_ENV['APP_ENV'] ?? 'production',

    'debug' => filter_var(
        $_ENV['APP_DEBUG'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    ),

    'url' => $_ENV['APP_URL'] ?? null,

    /*
     * Ruta base desde la que se publica NovaSysCore.
     *
     * Desarrollo XAMPP:
     * /NovaSysCore/public
     *
     * Producción con DocumentRoot apuntando a /public:
     * vacío
     */
    'base_path' => rtrim(
        $_ENV['APP_BASE_PATH'] ?? '',
        '/'
    ),

];