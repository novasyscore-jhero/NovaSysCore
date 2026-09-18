<?php

use NovaSysCore\Url;
use NovaSysCore\Security\CsrfTokenManager;

$csrf = new CsrfTokenManager();

$csrfToken = $csrf->token();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Nuevo usuario | NovaSysCore</title>
</head>

<body style="
    font-family:system-ui,sans-serif;
    margin:0;
    padding:40px;
    background:#f4f7fb;
    color:#111827;
">

    <div style="
        max-width:900px;
        margin:0 auto;
    ">

        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:30px;
        ">
            <div>
                <h1 style="margin:0;">
                    Nuevo usuario
                </h1>

                <p style="
                    margin-top:8px;
                    color:#6b7280;
                ">
                    Incorpora un usuario a la empresa actual.
                </p>
            </div>

            <a
                href="<?= htmlspecialchars(
                    Url::to('/users'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                Volver a usuarios
            </a>
        </div>

        <form
            method="POST"
            action="<?= htmlspecialchars(
                Url::to('/users'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            style="
                background:white;
                border-radius:12px;
                padding:30px;
            "
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

            <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fit, minmax(260px, 1fr));
                gap:20px;
            ">

                <div>
                    <label for="name">
                        Nombre
                    </label>

                    <input
                        id="name"
                        name="name"
                        type="text"
                        maxlength="100"
                        required
                        autocomplete="given-name"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

                <div>
                    <label for="last_name">
                        Apellidos
                    </label>

                    <input
                        id="last_name"
                        name="last_name"
                        type="text"
                        maxlength="150"
                        autocomplete="family-name"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

                <div>
                    <label for="display_name">
                        Nombre para mostrar
                    </label>

                    <input
                        id="display_name"
                        name="display_name"
                        type="text"
                        maxlength="150"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

                <div>
                    <label for="email">
                        Correo electrónico
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="180"
                        required
                        autocomplete="email"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

                <div>
                    <label for="phone">
                        Teléfono
                    </label>

                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        maxlength="50"
                        autocomplete="tel"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

                <div>
                    <label for="password">
                        Contraseña inicial
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                        style="
                            width:100%;
                            box-sizing:border-box;
                            margin-top:7px;
                            padding:11px 12px;
                            border:1px solid #d1d5db;
                            border-radius:8px;
                        "
                    >
                </div>

            </div>

            <div style="
                margin-top:30px;
                display:flex;
                justify-content:flex-end;
                gap:12px;
            ">

                <a
                    href="<?= htmlspecialchars(
                        Url::to('/users'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    style="
                        padding:11px 18px;
                        border:1px solid #d1d5db;
                        border-radius:8px;
                        text-decoration:none;
                        color:#111827;
                    "
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    style="
                        padding:11px 18px;
                        border:0;
                        border-radius:8px;
                        background:#111827;
                        color:white;
                        cursor:pointer;
                    "
                >
                    Crear usuario
                </button>

            </div>

        </form>

    </div>

</body>
</html>