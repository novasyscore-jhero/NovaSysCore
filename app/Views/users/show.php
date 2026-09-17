<?php

use NovaSysCore\Url;

$displayName =
    $user['display_name']
    ?: trim(
        ($user['name'] ?? '')
        . ' '
        . ($user['last_name'] ?? '')
    );

if ($displayName === '') {
    $displayName = $user['email'];
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

    <title>
        <?= htmlspecialchars(
            $displayName,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | NovaSysCore
    </title>
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
                    <?= htmlspecialchars(
                        $displayName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h1>

                <p style="
                    margin-top:8px;
                    color:#6b7280;
                ">
                    Información del usuario
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

        <div style="
            background:white;
            border-radius:12px;
            padding:30px;
        ">

            <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fit, minmax(220px, 1fr));
                gap:25px;
            ">

                <div>
                    <div style="
                        font-size:13px;
                        color:#6b7280;
                        margin-bottom:6px;
                    ">
                        ID
                    </div>

                    <strong>
                        <?= (int) $user['id'] ?>
                    </strong>
                </div>

                <div>
                    <div style="
                        font-size:13px;
                        color:#6b7280;
                        margin-bottom:6px;
                    ">
                        Nombre
                    </div>

                    <strong>
                        <?= htmlspecialchars(
                            $displayName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <div style="
                        font-size:13px;
                        color:#6b7280;
                        margin-bottom:6px;
                    ">
                        Correo electrónico
                    </div>

                    <strong>
                        <?= htmlspecialchars(
                            $user['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

                <div>
                    <div style="
                        font-size:13px;
                        color:#6b7280;
                        margin-bottom:6px;
                    ">
                        Estado
                    </div>

                    <strong>
                        <?= $user['status'] === 'active'
                            ? 'Activo'
                            : htmlspecialchars(
                                $user['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                    </strong>
                </div>

            </div>

        </div>

    </div>

</body>
</html>