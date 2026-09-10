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
        Seleccionar empresa | NovaSysCore
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
            Selecciona una empresa
        </h1>

        <p>
            Elige la empresa con la que deseas trabajar.
        </p>

        <form
            method="POST"
            action="<?= htmlspecialchars(
                Url::to('/context/select'),
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

            <?php foreach ($companies as $company): ?>

                <label style="
                    display:block;
                    margin-bottom:12px;
                    padding:16px;
                    background:white;
                    border:1px solid #dbe2ea;
                    border-radius:8px;
                    cursor:pointer;
                ">

                    <input
                        type="radio"
                        name="company_id"
                        value="<?= (int) $company['id'] ?>"
                        required
                    >

                    <strong>
                        <?= htmlspecialchars(
                            $company['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                </label>

            <?php endforeach; ?>

            <button
                type="submit"
                style="
                    margin-top:20px;
                    padding:12px 20px;
                    cursor:pointer;
                "
            >
                Continuar
            </button>

        </form>

    </main>

</body>
</html>