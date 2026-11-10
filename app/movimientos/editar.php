
<?php
require_once __DIR__ . '/../../config/sesion.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar que el usuario tenga sesión.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$error = '';

// Obtener el ID del movimiento.
$id = filter_var(
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['id'] ?? null)
        : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Movimiento no válido.');
}

// Buscar exclusivamente movimientos del usuario.
$consulta = $conexion->prepare(
    "SELECT id, tipo, categoria_id, monto,
            descripcion, fecha
     FROM movimientos
     WHERE id = ? AND usuario_id = ?"
);
$consulta->execute([$id, $usuarioId]);
$movimiento = $consulta->fetch();

if (!$movimiento) {
    http_response_code(404);
    exit('Movimiento no encontrado.');
}

// Consultar categorías del usuario.
$consulta = $conexion->prepare(
    "SELECT id, nombre, tipo
     FROM categorias
     WHERE usuario_id = ?
     ORDER BY tipo, nombre"
);
$consulta->execute([$usuarioId]);
$categorias = $consulta->fetchAll();

// Procesar los cambios.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validarCsrf()) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $tipo = $_POST['tipo'] ?? '';
    $categoriaId = filter_var(
        $_POST['categoria_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $monto = trim($_POST['monto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'] ?? '';

    $fechaValida = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
        $error = 'Selecciona un tipo de movimiento válido.';
    } elseif (
        !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $monto)
        || (float)$monto <= 0
    ) {
        $error = 'Ingresa un monto válido mayor a cero.';
    } elseif (
        !$fechaValida ||
        $fechaValida->format('Y-m-d') !== $fecha
    ) {
        $error = 'La fecha no es válida.';
    } elseif (mb_strlen($descripcion) > 255) {
        $error = 'La descripción es demasiado larga.';
    } elseif (!$categoriaId || $categoriaId <= 0) {
        $error = 'Selecciona una categoría.';
    } else {

        // Validar propiedad y tipo de la categoría.
        $consulta = $conexion->prepare(
            "SELECT id
             FROM categorias
             WHERE id = ?
             AND usuario_id = ?
             AND tipo = ?"
        );

        $consulta->execute([
            $categoriaId,
            $usuarioId,
            $tipo
        ]);

        if (!$consulta->fetch()) {
            $error = 'La categoría seleccionada no es válida.';
        } else {

            $actualizar = $conexion->prepare(
                "UPDATE movimientos
                 SET tipo = ?,
                     categoria_id = ?,
                     monto = ?,
                     descripcion = ?,
                     fecha = ?
                 WHERE id = ?
                 AND usuario_id = ?"
            );

            $actualizar->execute([
                $tipo,
                $categoriaId,
                $monto,
                $descripcion,
                $fecha,
                $id,
                $usuarioId
            ]);

            header('Location: ../../dashboard.php?editado=1');
            exit;
        }
    }

    // Conservar valores capturados si hubo un error.
    $movimiento['tipo'] = $tipo;
    $movimiento['categoria_id'] = $categoriaId;
    $movimiento['monto'] = $monto;
    $movimiento['descripcion'] = $descripcion;
    $movimiento['fecha'] = $fecha;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Editar movimiento - MiControl Financiero</title>

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

<main class="container py-5" style="max-width:650px;">

    <div class="card shadow-sm">

        <div class="card-body p-4">

            <h3>Editar movimiento</h3>

            <p class="text-muted">
                Modifica los datos de tu ingreso o gasto.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= escapar($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <input type="hidden" name="csrf_token"
                    value="<?= escapar($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="id"
                    value="<?= (int)$movimiento['id'] ?>">

                <label class="form-label mt-3">
                    Tipo de movimiento
                </label>

                <select name="tipo" id="tipo"
                        class="form-select" required>

                    <option value="ingreso"
                        <?= $movimiento['tipo'] === 'ingreso'
                            ? 'selected' : '' ?>>
                        Ingreso
                    </option>

                    <option value="gasto"
                        <?= $movimiento['tipo'] === 'gasto'
                            ? 'selected' : '' ?>>
                        Gasto
                    </option>

                </select>

                <label class="form-label mt-3">
                    Categoría
                </label>

                <select name="categoria_id" id="categoria"
                        class="form-select" required>
                </select>

                <label class="form-label mt-3">
                    Monto (MXN)
                </label>

                <input type="number"
                       name="monto"
                       class="form-control"
                       min="0.01"
                       max="9999999999.99"
                       step="0.01"
                       value="<?= escapar($movimiento['monto']) ?>"
                       required>

                <label class="form-label mt-3">
                    Fecha
                </label>

                <input type="date"
                       name="fecha"
                       class="form-control"
                       value="<?= escapar($movimiento['fecha']) ?>"
                       required>

                <label class="form-label mt-3">
                    Descripción
                </label>

                <input type="text"
                       name="descripcion"
                       class="form-control"
                       maxlength="255"
                       value="<?= escapar(
                           $movimiento['descripcion'] ?? ''
                       ) ?>">

                <div class="d-flex gap-2 mt-4">
                    <button type="submit"
                            class="btn btn-success">
                        Guardar cambios
                    </button>

                    <a href="../../dashboard.php"
                       class="btn btn-secondary">
                        Cancelar
                    </a>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
const categorias = <?= json_encode(
    $categorias,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const categoriaActual = <?= json_encode(
    (string)$movimiento['categoria_id']
) ?>;

const tipo = document.getElementById('tipo');
const selector = document.getElementById('categoria');

function actualizarCategorias() {
    selector.replaceChildren();

    categorias
        .filter(c => c.tipo === tipo.value)
        .forEach(c => {
            const opcion = document.createElement('option');
            opcion.value = c.id;
            opcion.textContent = c.nombre;

            if (String(c.id) === categoriaActual) {
                opcion.selected = true;
            }

            selector.appendChild(opcion);
        });
}

tipo.addEventListener('change', actualizarCategorias);
actualizarCategorias();
</script>

</body>
</html>
