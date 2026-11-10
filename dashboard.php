<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: app/auth/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$error = '';

// categorías iniciales para el usuario
$categoriasIniciales = [
    ['Salario', 'ingreso'],
    ['Ventas', 'ingreso'],
    ['Otros ingresos', 'ingreso'],
    ['Alimentación', 'gasto'],
    ['Transporte', 'gasto'],
    ['Entretenimiento', 'gasto'],
    ['Servicios', 'gasto'],
    ['Compras', 'gasto'],
    ['Otros gastos', 'gasto']
];

$insertarCategoria = $conexion->prepare(
    "INSERT INTO categorias (usuario_id, nombre, tipo)
     SELECT ?, ?, ?
     WHERE NOT EXISTS (
         SELECT 1 FROM categorias
         WHERE usuario_id = ? AND nombre = ? AND tipo = ?
     )"
);

foreach ($categoriasIniciales as [$categoriaNombre, $categoriaTipo]) {
    $insertarCategoria->execute([
        $usuarioId, $categoriaNombre, $categoriaTipo,
        $usuarioId, $categoriaNombre, $categoriaTipo
    ]);
}

// Registrar movimientos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf()) {
        $error = 'Solicitud no válida. Recarga la página.';
    } else {
        $tipo = $_POST['tipo'] ?? '';
        $monto = trim($_POST['monto'] ?? '');
        $categoriaId = filter_var(
            $_POST['categoria_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fecha = $_POST['fecha'] ?? '';

        $fechaValida = DateTimeImmutable::createFromFormat(
            '!Y-m-d', $fecha
        );

        if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
            $error = 'Selecciona un tipo válido.';
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
        } elseif (!$categoriaId) {
            $error = 'Selecciona una categoría.';
        } else {
            // Comprobar que la categoría sea del usuario
            $consulta = $conexion->prepare(
                "SELECT id FROM categorias
                 WHERE id = ? AND usuario_id = ? AND tipo = ?"
            );
            $consulta->execute([
                $categoriaId, $usuarioId, $tipo
            ]);

            if (!$consulta->fetch()) {
                $error = 'La categoría seleccionada no es válida.';
            } else {
                $guardar = $conexion->prepare(
                    "INSERT INTO movimientos
                    (usuario_id, categoria_id, tipo,
                     monto, descripcion, fecha)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $guardar->execute([
                    $usuarioId,
                    $categoriaId,
                    $tipo,
                    $monto,
                    $descripcion,
                    $fecha
                ]);

                header('Location: dashboard.php?guardado=1');
                exit;
            }
        }
    }
}

// Consultar las categorías del usuario
$consulta = $conexion->prepare(
    "SELECT id, nombre, tipo FROM categorias
     WHERE usuario_id = ?
     ORDER BY tipo, nombre"
);
$consulta->execute([$usuarioId]);
$categorias = $consulta->fetchAll();

// Calcular ingresos y gastos
$consulta = $conexion->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN tipo = 'ingreso'
            THEN monto ELSE 0 END), 0) AS ingresos,
        COALESCE(SUM(CASE WHEN tipo = 'gasto'
            THEN monto ELSE 0 END), 0) AS gastos
     FROM movimientos WHERE usuario_id = ?"
);
$consulta->execute([$usuarioId]);
$totales = $consulta->fetch();

$ingresos = (float)$totales['ingresos'];
$gastos = (float)$totales['gastos'];
$saldo = $ingresos - $gastos;

// Consultar los últimos movimientos
$consulta = $conexion->prepare(
    "SELECT m.id, m.tipo, m.monto, m.descripcion,
            m.fecha, c.nombre AS categoria
     FROM movimientos m
     INNER JOIN categorias c ON c.id = m.categoria_id
     WHERE m.usuario_id = ?
     ORDER BY m.fecha DESC, m.id DESC
     LIMIT 10"
);
$consulta->execute([$usuarioId]);
$movimientos = $consulta->fetchAll();

function dinero($cantidad) {
    return '$' . number_format((float)$cantidad, 2) . ' MXN';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Dashboard - MiControl Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <span class="navbar-brand">
            MiControl Financiero
        </span>
        <a href="app/auth/logout.php"
           class="btn btn-outline-light btn-sm">
            Cerrar sesión
        </a>
    </div>
</nav>

<main class="container py-4">
    <h2>Bienvenido, <?= escapar($nombre) ?></h2>
    <p class="text-muted">
        Administra tus finanzas personales.
    </p>

    <?php if (isset($_GET['guardado'])): ?>
        <div class="alert alert-success">
            Movimiento registrado correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['eliminado'])): ?>
        <div class="alert alert-success">
            Movimiento eliminado correctamente.
        </div>
    <?php endif; ?>


    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Ingresos totales</h6>
                    <h3 class="text-success">
                        <?= dinero($ingresos) ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Gastos totales</h6>
                    <h3 class="text-danger">
                        <?= dinero($gastos) ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="text-muted">Saldo disponible</h6>
                    <h3>
                        <?= dinero($saldo) ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Registrar movimiento</h5>

                    <form method="POST">
                        <input type="hidden" name="csrf_token"
                            value="<?= escapar($_SESSION['csrf_token']) ?>">

                        <label class="form-label mt-3">Tipo</label>
                        <select name="tipo" id="tipo"
                                class="form-select" required>
                            <option value="ingreso">Ingreso</option>
                            <option value="gasto">Gasto</option>
                        </select>

                        <label class="form-label mt-3">Categoría</label>
                        <select name="categoria_id" id="categoria"
                                class="form-select" required>
                        </select>

                        <label class="form-label mt-3">Monto (MXN)</label>
                        <input type="number" name="monto"
                               class="form-control"
                               min="0.01" max="9999999999.99"
                               step="0.01" required>

                        <label class="form-label mt-3">Fecha</label>
                        <input type="date" name="fecha"
                               class="form-control"
                               value="<?= date('Y-m-d') ?>"
                               required>

                        <label class="form-label mt-3">
                            Descripción
                        </label>
                        <input type="text" name="descripcion"
                               class="form-control"
                               maxlength="255"
                               placeholder="Ej. Pago de trabajo">

                        <button type="submit"
                                class="btn btn-success w-100 mt-4">
                            Guardar movimiento
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Últimos movimientos</h5>
                    <div class="table-responsive">
                        <table class="table table-striped mt-3">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $m): ?>
                                    <tr>
                                        <td><?= escapar($m['fecha']) ?></td>
                                        <td><?= escapar($m['categoria']) ?></td>
                                        <td><?= escapar($m['descripcion']) ?></td>
                                        <td class="<?= $m['tipo'] === 'ingreso'
                                            ? 'text-success' : 'text-danger' ?>">
                                            <?= $m['tipo'] === 'ingreso'
                                                ? '+' : '-' ?>
                                            <?= dinero($m['monto']) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form
                                                    action="app/movimientos/eliminar.php"
                                                    method="POST"
                                                    onsubmit="return confirm('¿Seguro que deseas eliminar este movimiento?');">

                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= escapar($_SESSION['csrf_token']) ?>">

                                                    <input type="hidden" name="id"
                                                        value="<?= (int)$m['id'] ?>">

                                                    <button type="submit"
                                                        class="btn btn-sm btn-outline-danger">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>

                                <?php if (!$movimientos): ?>
                                    <tr>
                                        <td colspan="5"
                                            class="text-center text-muted">
                                            Todavía no hay movimientos.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const categorias = <?= json_encode(
    $categorias,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
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
            selector.appendChild(opcion);
        });
}

tipo.addEventListener('change', actualizarCategorias);
actualizarCategorias();
</script>

</body>
</html>
