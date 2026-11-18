
<?php
require_once __DIR__ . '/../../config/sesion.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCsrf()) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $accion = $_POST['accion'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $objetivo = trim($_POST['monto_objetivo'] ?? '');
    $actual = trim($_POST['monto_actual'] ?? '0');
    $fecha = $_POST['fecha_limite'] ?? '';

    $montoValido = function ($valor) {
        return preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $valor
        ) === 1;
    };

    if ($accion === 'crear') {

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $error = 'Escribe un nombre válido.';
        } elseif (
            !$montoValido($objetivo) ||
            (float)$objetivo <= 0
        ) {
            $error = 'El monto objetivo no es válido.';
        } elseif (
            !$montoValido($actual) ||
            (float)$actual > (float)$objetivo
        ) {
            $error = 'El ahorro actual no es válido.';
        } else {

            $fechaValida = null;

            if ($fecha !== '') {
                $fechaValida = DateTimeImmutable::createFromFormat(
                    '!Y-m-d', $fecha
                );

                if (
                    !$fechaValida ||
                    $fechaValida->format('Y-m-d') !== $fecha
                ) {
                    $error = 'La fecha no es válida.';
                }
            }

            if ($error === '') {
                $consulta = $conexion->prepare(
                    "INSERT INTO metas_ahorro
                    (usuario_id, nombre, monto_objetivo,
                     monto_actual, fecha_limite)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $consulta->execute([
                    $usuarioId,
                    $nombre,
                    $objetivo,
                    $actual,
                    $fecha === '' ? null : $fecha
                ]);

                header('Location: index.php?creada=1');
                exit;
            }
        }

    } elseif ($accion === 'eliminar') {

        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id <= 0) {
            $error = 'Meta no válida.';
        } else {
            $consulta = $conexion->prepare(
                "DELETE FROM metas_ahorro
                 WHERE id = ? AND usuario_id = ?"
            );

            $consulta->execute([$id, $usuarioId]);

            header('Location: index.php?eliminada=1');
            exit;
        }

    } else {
        $error = 'Acción no permitida.';
    }
}

// Consultar las metas del usuario.
$consulta = $conexion->prepare(
    "SELECT * FROM metas_ahorro
     WHERE usuario_id = ?
     ORDER BY id DESC"
);
$consulta->execute([$usuarioId]);
$metas = $consulta->fetchAll();

function formatoDinero($cantidad) {
    return '$' . number_format((float)$cantidad, 2);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Metas de ahorro - MiControl Financiero</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <span class="navbar-brand">
            MiControl Financiero
        </span>

        <a href="../../dashboard.php"
           class="btn btn-outline-light btn-sm">
            Volver al dashboard
        </a>
    </div>
</nav>

<main class="container py-4">

    <h2>Metas de ahorro</h2>

    <p class="text-muted">
        Organiza tus objetivos financieros.
    </p>

    <?php if (isset($_GET['creada'])): ?>
        <div class="alert alert-success">
            Meta creada correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['actualizada'])): ?>
        <div class="alert alert-success">
            Meta actualizada correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['eliminada'])): ?>
        <div class="alert alert-success">
            Meta eliminada correctamente.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-5">

            <div class="card shadow-sm">
                <div class="card-body">

                    <h5>Crear meta de ahorro</h5>

                    <form method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= escapar(
                                   $_SESSION['csrf_token']
                               ) ?>">

                        <input type="hidden"
                               name="accion"
                               value="crear">

                        <label class="form-label mt-3">
                            Nombre de la meta
                        </label>

                        <input type="text"
                               name="nombre"
                               class="form-control"
                               maxlength="150"
                               placeholder="Ej. Comprar laptop"
                               required>

                        <label class="form-label mt-3">
                            Cantidad objetivo
                        </label>

                        <input type="number"
                               name="monto_objetivo"
                               class="form-control"
                               min="0.01"
                               max="9999999999.99"
                               step="0.01"
                               required>

                        <label class="form-label mt-3">
                            Cantidad ahorrada
                        </label>

                        <input type="number"
                               name="monto_actual"
                               class="form-control"
                               value="0"
                               min="0"
                               max="9999999999.99"
                               step="0.01"
                               required>

                        <label class="form-label mt-3">
                            Fecha límite (opcional)
                        </label>

                        <input type="date"
                               name="fecha_limite"
                               class="form-control">

                        <button type="submit"
                                class="btn btn-success w-100 mt-4">
                            Crear meta
                        </button>

                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">

            <h5>Mis metas</h5>

            <?php if (!$metas): ?>
                <div class="alert alert-info">
                    Todavía no tienes metas de ahorro.
                </div>
            <?php endif; ?>

            <?php foreach ($metas as $meta): ?>

                <?php
                    $porcentaje = $meta['monto_objetivo'] > 0
                        ? ($meta['monto_actual'] /
                           $meta['monto_objetivo']) * 100
                        : 0;

                    $porcentaje = min(100, $porcentaje);
                ?>

                <div class="card shadow-sm mb-3">
                    <div class="card-body">

                        <h5><?= escapar($meta['nombre']) ?></h5>

                        <p class="mb-2">
                            Ahorrado:
                            <strong>
                                <?= formatoDinero(
                                    $meta['monto_actual']
                                ) ?>
                            </strong>
                            de
                            <?= formatoDinero(
                                $meta['monto_objetivo']
                            ) ?> MXN
                        </p>

                        <div class="progress mb-2"
                             style="height:20px;">

                            <div class="progress-bar bg-success"
                                 style="width:<?= $porcentaje ?>%">
                                <?= number_format(
                                    $porcentaje, 1
                                ) ?>%
                            </div>
                        </div>

                        <?php if ($meta['fecha_limite']): ?>
                            <p class="text-muted small">
                                Fecha límite:
                                <?= escapar(
                                    $meta['fecha_limite']
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <a href="editar.php?id=<?= (int)$meta['id'] ?>"
                        class="btn btn-outline-primary btn-sm mb-2">
                            Editar meta
                        </a>

                        <form method="POST"
                              onsubmit="return confirm('¿Eliminar esta meta?');">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= escapar(
                                       $_SESSION['csrf_token']
                                   ) ?>">

                            <input type="hidden"
                                   name="accion"
                                   value="eliminar">

                            <input type="hidden"
                                   name="id"
                                   value="<?= (int)$meta['id'] ?>">

                            <button
                                class="btn btn-outline-danger btn-sm">
                                Eliminar
                            </button>
                        </form>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>
    </div>
</main>

</body>
</html>
