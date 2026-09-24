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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

            <a href="<?= htmlspecialchars(
                Url::to('/users'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
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
                        Estado global
                    </div>

                    <strong>
                        <?= $user['user_status'] === 'active'
                        ? 'Activo'
                        : htmlspecialchars(
                            $user['user_status'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

            </div>

        </div>

                <!--
        =====================================================
        MEMBRESÍA EMPRESARIAL
        =====================================================
        -->

        <div style="
            background:white;
            border-radius:12px;
            padding:30px;
            margin-top:25px;
        ">

            <div style="
                margin-bottom:25px;
            ">
                <h2 style="
                    margin:0;
                    font-size:20px;
                ">
                    Membresía empresarial
                </h2>

                <p style="
                    margin:8px 0 0;
                    color:#6b7280;
                ">
                    Relación del usuario con la empresa actual
                </p>
            </div>

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
                        Empresa
                    </div>

                    <strong>
                        <?= htmlspecialchars(
                            $user['company_name'],
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
                        Estado de membresía
                    </div>

                    <strong>
                        <?= $user['membership_status'] === 'active'
                            ? 'Activa'
                            : htmlspecialchars(
                                $user['membership_status'],
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
                        Miembro desde
                    </div>

                    <strong>
                        <?= htmlspecialchars(
                            $user['membership_created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

            </div>

        </div>

        <!--
        =====================================================
        ROLES EMPRESARIALES
        =====================================================
        -->

        <div style="
            background:white;
            border-radius:12px;
            padding:30px;
            margin-top:25px;
        ">

            <div style="
                margin-bottom:25px;
            ">
                <h2 style="
                    margin:0;
                    font-size:20px;
                ">
                    Roles empresariales
                </h2>

                <p style="
                    margin:8px 0 0;
                    color:#6b7280;
                ">
                    Roles asignados al usuario dentro de la empresa actual
                </p>
            </div>

            <?php if (empty($companyRoles)): ?>

                <div style="
                    padding:18px;
                    background:#f9fafb;
                    border-radius:8px;
                    color:#6b7280;
                ">
                    Este usuario no tiene roles empresariales asignados en
                    <strong style="color:#111827;">
                        <?= htmlspecialchars(
                            $user['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>.
                </div>

            <?php else: ?>

                <?php foreach ($companyRoles as $role): ?>

                    <div style="
                        border:1px solid #e5e7eb;
                        border-radius:10px;
                        padding:20px;
                        margin-bottom:15px;
                    ">

                        <div style="
                            font-size:18px;
                            font-weight:700;
                            margin-bottom:18px;
                        ">
                            <?= htmlspecialchars(
                                $role['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                        <div style="
                            display:grid;
                            grid-template-columns:
                                repeat(auto-fit, minmax(220px, 1fr));
                            gap:20px;
                        ">

                            <div>
                                <div style="
                                    font-size:13px;
                                    color:#6b7280;
                                    margin-bottom:6px;
                                ">
                                    Alcance de sucursales
                                </div>

                                <strong>
                                    <?=
                                        $role['branch_scope'] === 'all'
                                            ? 'Todas las sucursales'
                                            : 'Sucursales seleccionadas'
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div style="
                                    font-size:13px;
                                    color:#6b7280;
                                    margin-bottom:6px;
                                ">
                                    Asignado desde
                                </div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $role['assigned_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </div>

                            <?php if (
                                !empty($role['description'])
                            ): ?>

                                <div>
                                    <div style="
                                        font-size:13px;
                                        color:#6b7280;
                                        margin-bottom:6px;
                                    ">
                                        Descripción
                                    </div>

                                    <span>
                                        <?= htmlspecialchars(
                                            $role['description'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>
