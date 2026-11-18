
<?php
require_once __DIR__ . '/../../config/sesion.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$error = '';

$id = filter_var(
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['id'] ?? null)
        : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Meta no válida.');
}

// Buscar únicamente las metas del usuario.
$consulta = $conexion->prepare(
    "SELECT id, nombre, monto_objetivo,
            monto_actual, fecha_limite
     FROM metas_ahorro
     WHERE id = ? AND usuario_id = ?"
);

$consulta->execute([$id, $usuarioId]);
$meta = $consulta->fetch();

if (!$meta) {
    http_response_code(404);
    exit('Meta no encontrada.');
}

// Procesar actualización.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCsrf()) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $objetivo = trim($_POST['monto_objetivo'] ?? '');
    $actual = trim($_POST['monto_actual'] ?? '');
    $fecha = $_POST['fecha_limite'] ?? '';

    $montoValido = function ($valor) {
        return preg_match(
            '/^\d{1,10}(\.\d{1,2})?$/',
            $valor
        ) === 1;
    };

    if ($nombre === '' || mb_strlen($nombre) > 150) {
        $error = 'Ingresa un nombre válido.';
    } elseif (
        !$montoValido($objetivo) ||
        (float)$objetivo <= 0
    ) {
        $error = 'La cantidad objetivo no es válida.';
    } elseif (
        !$montoValido($actual) ||
        (float)$actual > (float)$objetivo
    ) {
        $error = 'La cantidad ahorrada debe estar entre cero y el objetivo.';
    } else {

        if ($fecha !== '') {
            $fechaValida = DateTimeImmutable::createFromFormat(
                '!Y-m-d', $fecha
            );

            if (
                !$fechaValida ||
                $fechaValida->format('Y-m-d') !== $fecha
            ) {
                $error = 'La fecha límite no es válida.';
            }
        }

        if ($error === '') {
            $actualizar = $conexion->prepare(
                "UPDATE metas_ahorro
                 SET nombre = ?,
                     monto_objetivo = ?,
                     monto_actual = ?,
                     fecha_limite = ?
                 WHERE id = ? AND usuario_id = ?"
            );

            $actualizar->execute([
                $nombre,
                $objetivo,
                $actual,
                $fecha === '' ? null : $fecha,
                $id,
                $usuarioId
            ]);

            header('Location: index.php?actualizada=1');
            exit;
        }
    }

    // Mantener datos escritos si hay errores.
    $meta['nombre'] = $nombre;
    $meta['monto_objetivo'] = $objetivo;
    $meta['monto_actual'] = $actual;
    $meta['fecha_limite'] = $fecha;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Editar meta - MiControl Financiero</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <span class="navbar-brand">
            MiControl Financiero
        </span>

        <a href="index.php"
           class="btn btn-outline-light btn-sm">
            Volver a mis metas
        </a>
    </div>
</nav>

<main class="container py-5" style="max-width:650px;">

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <h3>Editar meta de ahorro</h3>

            <p class="text-muted">
                Actualiza tu objetivo y el dinero ahorrado.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= escapar($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escapar($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="id"
                       value="<?= (int)$meta['id'] ?>">

                <label class="form-label mt-3">
                    Nombre de la meta
                </label>

                <input type="text"
                       name="nombre"
                       class="form-control"
                       maxlength="150"
                       value="<?= escapar($meta['nombre']) ?>"
                       required>

                <label class="form-label mt-3">
                    Cantidad objetivo (MXN)
                </label>

                <input type="number"
                       name="monto_objetivo"
                       class="form-control"
                       min="0.01"
                       max="9999999999.99"
                       step="0.01"
                       value="<?= escapar($meta['monto_objetivo']) ?>"
                       required>

                <label class="form-label mt-3">
                    Cantidad ahorrada (MXN)
                </label>

                <input type="number"
                       name="monto_actual"
                       class="form-control"
                       min="0"
                       max="9999999999.99"
                       step="0.01"
                       value="<?= escapar($meta['monto_actual']) ?>"
                       required>

                <label class="form-label mt-3">
                    Fecha límite (opcional)
                </label>

                <input type="date"
                       name="fecha_limite"
                       class="form-control"
                       value="<?= escapar($meta['fecha_limite'] ?? '') ?>">

                <div class="d-flex gap-2 mt-4">

                    <button type="submit"
                            class="btn btn-success">
                        Guardar cambios
                    </button>

                    <a href="index.php"
                       class="btn btn-secondary">
                        Cancelar
                    </a>

                </div>
            </form>

        </div>
    </div>
</main>

</body>
</html>
