<?php

use NovaSysCore\Url;
use NovaSysCore\Security\CsrfTokenManager;

$errorMessage = null;

$csrf = new CsrfTokenManager();

$csrfToken = $csrf->token();

if (
    isset($error)
    && $error === 'invalid_credentials'
) {
    $errorMessage = 'El correo o la contraseña son incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión | NovaSysCore</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f4f7fb 0%,
                    #e9eef7 100%
                );

            color: #1f2937;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .brand {
            margin-bottom: 24px;
            text-align: center;
        }

        .brand h1 {
            margin: 0;

            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.5px;

            color: #111827;
        }

        .brand p {
            margin: 8px 0 0;

            font-size: 14px;

            color: #6b7280;
        }

        .login-card {
            padding: 32px;

            background: #ffffff;

            border:
                1px solid
                rgba(17, 24, 39, 0.08);

            border-radius: 16px;

            box-shadow:
                0 20px 50px
                rgba(15, 23, 42, 0.08);
        }

        .login-card h2 {
            margin:
                0
                0
                8px;

            font-size: 22px;
        }

        .login-card .subtitle {
            margin:
                0
                0
                24px;

            font-size: 14px;
            line-height: 1.5;

            color: #6b7280;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: 600;

            color: #374151;
        }

        input {
            width: 100%;

            padding:
                12px
                14px;

            border:
                1px solid
                #d1d5db;

            border-radius: 10px;

            font-size: 15px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.12);
        }

        .error {
            margin-bottom: 18px;

            padding:
                11px
                13px;

            border:
                1px solid
                #fecaca;

            border-radius: 10px;

            background: #fef2f2;

            color: #991b1b;

            font-size: 14px;
        }

        button {
            width: 100%;

            padding:
                13px
                16px;

            border: 0;
            border-radius: 10px;

            background: #111827;

            color: #ffffff;

            font-size: 15px;
            font-weight: 600;

            cursor: pointer;

            transition:
                transform 0.15s ease,
                opacity 0.15s ease;
        }

        button:hover {
            opacity: 0.92;
        }

        button:active {
            transform: translateY(1px);
        }

        .footer {
            margin-top: 20px;

            text-align: center;

            font-size: 12px;

            color: #9ca3af;
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="brand">
        <h1>NovaSysCore</h1>

        <p>
            Acceso al sistema administrativo
        </p>
    </div>

    <div class="login-card">

        <h2>Iniciar sesión</h2>

        <p class="subtitle">
            Ingresa tus credenciales para continuar.
        </p>

        <?php if ($errorMessage !== null): ?>

            <div class="error">
                <?= htmlspecialchars(
                    $errorMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

            <form
                method="POST"
                action="<?= htmlspecialchars(
                    Url::to('/login'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                autocomplete="on"
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

            <div class="form-group">

                <label for="email">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >

            </div>

            <button type="submit">
                Iniciar sesión
            </button>

        </form>

    </div>

    <div class="footer">
        NovaSysCore
    </div>

</div>

</body>
</html>