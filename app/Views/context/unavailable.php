<?php

use NovaSysCore\Security\CsrfTokenManager;
use NovaSysCore\Url;

$csrf =
    new CsrfTokenManager();

$csrfToken =
    $csrf->token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Sin acceso empresarial | NovaSysCore
    </title>
</head>

<body style="
    margin:0;
    font-family:system-ui,sans-serif;
    background:#f4f7fb;
    color:#111827;
">

    <main style="
        max-width:700px;
        margin:60px auto;
        padding:30px;
    ">

        <h1>
            Sin empresas disponibles
        </h1>

        <p>
            Tu usuario no tiene acceso actualmente
            a ninguna empresa activa.
        </p>

        <form
            method="POST"
            action="<?= htmlspecialchars(
                Url::to('/logout'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

            <input
                type="hidden"
                name="_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button type="submit">
                Cerrar sesión
            </button>

        </form>

    </main>

</body>
</html>