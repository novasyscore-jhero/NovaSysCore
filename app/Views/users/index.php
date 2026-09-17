<?php

use NovaSysCore\Url;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Usuarios | NovaSysCore</title>
</head>

<body style="
    font-family:system-ui,sans-serif;
    margin:0;
    padding:40px;
    background:#f4f7fb;
    color:#111827;
">

    <div style="
        max-width:1000px;
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
                    Usuarios
                </h1>

                <p style="
                    margin-top:8px;
                    color:#6b7280;
                ">
                    Usuarios activos de la empresa actual.
                </p>
            </div>

            <a href="<?= htmlspecialchars(
                Url::to('/dashboard'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
                Volver al dashboard
            </a>
        </div>

        <form method="GET" action="<?= htmlspecialchars(
            Url::to('/users'),
            ENT_QUOTES,
            'UTF-8'
                    ) ?>" style="
            display:flex;
            gap:10px;
            margin-bottom:20px;
        ">
            <input type="search" name="q" value="<?= htmlspecialchars(
                $search,
                ENT_QUOTES,
                'UTF-8'
            ) ?>" placeholder="Buscar por nombre o correo..." maxlength="100" style="
            flex:1;
            padding:11px 14px;
            border:1px solid #d1d5db;
            border-radius:8px;
            font-size:14px;
            ">

            <button type="submit" style="
                padding:11px 18px;
                border:0;
                border-radius:8px;
                background:#111827;
                color:white;
                cursor:pointer;
            ">
            Buscar
            </button>

            <?php if ($search !== ''): ?>
                <a href="<?= htmlspecialchars(
                    Url::to('/users'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>" style="
                display:flex;
                align-items:center;
                padding:11px 16px;
                border:1px solid #d1d5db;
                border-radius:8px;
                text-decoration:none;
                color:#111827;
                background:white;
            ">
                    Limpiar
                </a>
            <?php endif; ?>
        </form>

        <?php if (empty($users)): ?>

            <div style="
                background:white;
                padding:25px;
                border-radius:10px;
            ">
                <?php if ($search !== ''): ?>

                    No se encontraron usuarios para
                    <strong>
                        "<?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    </strong>.

                <?php else: ?>

                    No hay usuarios disponibles.

                <?php endif; ?>
            </div>

        <?php else: ?>

            <div style="
                background:white;
                border-radius:10px;
                overflow:hidden;
            ">

                <table style="
                    width:100%;
                    border-collapse:collapse;
                ">

                    <thead>
                        <tr style="
                            background:#f9fafb;
                            text-align:left;
                        ">
                            <th style="padding:14px;">
                                Nombre
                            </th>

                            <th style="padding:14px;">
                                Correo
                            </th>

                            <th style="padding:14px;">
                                Estado
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $displayName =
                                $user['display_name']
                                ?: trim(
                                    ($user['name'] ?? '')
                                    . ' '
                                    . ($user['last_name'] ?? '')
                                );

                            if ($displayName === '') {
                                $displayName =
                                    $user['email'];
                            }
                            ?>

                            <tr style="border-top:1px solid #e5e7eb;">

                                <td style="padding:14px;">
                                    <a href="<?= htmlspecialchars(
                                        Url::to('/users/' . (int) $user['id']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>" style="
                color:#2563eb;
                text-decoration:none;
                font-weight:600;
            ">
                                        <?= htmlspecialchars(
                                            $displayName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </a>
                                </td>

                                <td style="padding:14px;">
                                    <?= htmlspecialchars(
                                        $user['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td style="padding:14px;">
                                    <?= $user['status'] === 'active'
                                        ? 'Activo'
                                        : htmlspecialchars(
                                            $user['status'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>
                <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:20px;
                    padding:18px 14px;
                    border-top:1px solid #e5e7eb;
                ">
                <?php
                $searchQuery = $search !== ''
                    ? '&q=' . rawurlencode($search)
                    : '';
                ?>

                    <div style="
                        font-size:14px;
                        color:#6b7280;
                    ">
                        <?= number_format($totalUsers) ?>
                        <?= $totalUsers === 1 ? 'usuario' : 'usuarios' ?>

                        · Página <?= (int) $page ?>
                        de <?= (int) $totalPages ?>
                    </div>

                    <div style="
                        display:flex;
                        align-items:center;
                        gap:10px;
                    ">

                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(
                                Url::to(
                                    '/users?page=' . ($page - 1)
                                    . $searchQuery
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>" style="
                        padding:8px 14px;
                        border:1px solid #d1d5db;
                        border-radius:7px;
                        text-decoration:none;
                        color:#111827;
                        background:white;
                    ">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= htmlspecialchars(
                                Url::to(
                                    '/users?page=' . ($page + 1)
                                    . $searchQuery
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>" style="
                        padding:8px 14px;
                        border:1px solid #d1d5db;
                        border-radius:7px;
                        text-decoration:none;
                        color:#111827;
                        background:white;
                    ">
                                Siguiente
                            </a>
                        <?php endif; ?>

                    </div>

                </div>
            </div>

        <?php endif; ?>

    </div>

</body>

</html>