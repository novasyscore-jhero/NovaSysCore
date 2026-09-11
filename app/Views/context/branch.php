<?php

use NovaSysCore\Url;

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
        Seleccionar sucursal | NovaSysCore
    </title>

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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f6f7f9;
            color: #202223;
        }

        .card {
            width: 100%;
            max-width: 520px;

            margin: 24px;
            padding: 32px;

            background: #ffffff;

            border: 1px solid #dfe3e8;
            border-radius: 12px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.06);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;

            font-size: 24px;
        }

        .subtitle {
            margin-top: 0;
            margin-bottom: 24px;

            color: #6d7175;
        }

        .branch {
            display: block;

            margin-bottom: 12px;
            padding: 16px;

            border: 1px solid #dfe3e8;
            border-radius: 8px;

            cursor: pointer;
        }

        .branch:hover {
            background: #f6f6f7;
        }

        .branch input {
            margin-right: 10px;
        }

        button {
            width: 100%;

            margin-top: 12px;
            padding: 14px 18px;

            border: 0;
            border-radius: 8px;

            background: #202223;
            color: #ffffff;

            font-size: 15px;
            font-weight: 600;

            cursor: pointer;
        }

        button:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>

<div class="card">

    <h1>
        Selecciona una sucursal
    </h1>

    <p class="subtitle">
        Elige la sucursal en la que deseas trabajar.
    </p>

    <form
        method="POST"
        action="<?= htmlspecialchars(
            Url::to('/context/branch/select'),
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

        <?php foreach ($branches as $branch): ?>

            <label class="branch">

                <input
                    type="radio"
                    name="branch_id"
                    value="<?= (int) $branch['id'] ?>"
                    required
                >

                <?= htmlspecialchars(
                    $branch['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </label>

        <?php endforeach; ?>

        <button type="submit">
            Continuar
        </button>

    </form>

</div>

</body>
</html>