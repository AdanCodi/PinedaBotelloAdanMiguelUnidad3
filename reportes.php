
<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/database.php';

// Proteger el reporte.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: app/auth/login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

// Obtener el mes seleccionado.
$mes = $_GET['mes'] ?? date('Y-m');

// Validar año y mes.
if (
    !is_string($mes) ||
    !preg_match('/^(\d{4})-(\d{2})$/', $mes, $partes) ||
    !checkdate((int)$partes[2], 1, (int)$partes[1]) ||
    (int)$partes[1] < 1 ||
    (int)$partes[1] > 12
) {
    $mes = date('Y-m');
}

// Crear el rango de fechas.
$inicio = new DateTimeImmutable($mes . '-01');
$fin = $inicio->modify('+1 month');

$fechaInicio = $inicio->format('Y-m-d');
$fechaFin = $fin->format('Y-m-d');

// Calcular los totales del mes.
$consulta = $conexion->prepare(
    "SELECT
        COALESCE(SUM(
            CASE WHEN tipo = 'ingreso'
            THEN monto ELSE 0 END
        ), 0) AS ingresos,

        COALESCE(SUM(
            CASE WHEN tipo = 'gasto'
            THEN monto ELSE 0 END
        ), 0) AS gastos,

        COUNT(*) AS total_movimientos

     FROM movimientos
     WHERE usuario_id = ?
     AND fecha >= ?
     AND fecha < ?"
);

$consulta->execute([
    $usuarioId,
    $fechaInicio,
    $fechaFin
]);

$totales = $consulta->fetch();

$ingresos = (float)$totales['ingresos'];
$gastos = (float)$totales['gastos'];
$balance = $ingresos - $gastos;
$totalMovimientos = (int)$totales['total_movimientos'];

// Consultar gastos por categoría.
$consulta = $conexion->prepare(
    "SELECT
        c.nombre AS categoria,
        SUM(m.monto) AS total
     FROM movimientos m
     INNER JOIN categorias c
         ON m.categoria_id = c.id
         AND c.usuario_id = m.usuario_id
     WHERE m.usuario_id = ?
         AND m.tipo = 'gasto'
         AND m.fecha >= ?
         AND m.fecha < ?
     GROUP BY c.id, c.nombre
     ORDER BY total DESC"
);

$consulta->execute([
    $usuarioId,
    $fechaInicio,
    $fechaFin
]);

$gastosCategorias = $consulta->fetchAll();

// Consultar movimientos del mes.
$consulta = $conexion->prepare(
    "SELECT
        m.fecha,
        m.tipo,
        m.descripcion,
        m.monto,
        c.nombre AS categoria

     FROM movimientos m
     INNER JOIN categorias c
         ON m.categoria_id = c.id
         AND c.usuario_id = m.usuario_id

     WHERE m.usuario_id = ?
         AND m.fecha >= ?
         AND m.fecha < ?

     ORDER BY m.fecha DESC, m.id DESC"
);

$consulta->execute([
    $usuarioId,
    $fechaInicio,
    $fechaFin
]);

$movimientos = $consulta->fetchAll();

// Preparar información de las gráficas.
$etiquetas = array_column(
    $gastosCategorias,
    'categoria'
);

$valores = array_map(
    'floatval',
    array_column($gastosCategorias, 'total')
);

function formatoDineroReporte($valor) {
    return '$' . number_format(
        (float)$valor,
        2,
        '.',
        ','
    ) . ' MXN';
}

$nombreMes = $inicio->format('m/Y');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Reportes - MiControl Financiero</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

    <style>
        .grafica-contenedor {
            position: relative;
            height: 300px;
        }

        .card {
            border: none;
            border-radius: 12px;
        }

        @media print {
            .no-imprimir {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            .grafica-contenedor {
                height: 250px;
            }
        }
    </style>
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-success no-imprimir">
    <div class="container">

        <span class="navbar-brand">
            MiControl Financiero
        </span>

        <a href="dashboard.php"
           class="btn btn-outline-light btn-sm">
            Volver al dashboard
        </a>

    </div>
</nav>

<main class="container py-4">

    <div class="d-flex justify-content-between
                align-items-center flex-wrap gap-3 mb-4">

        <div>
            <h2>Reportes financieros</h2>

            <p class="text-muted mb-0">
                Usuario: <?= escapar($nombreUsuario) ?>
            </p>

            <p class="text-muted">
                Periodo: <?= escapar($nombreMes) ?>
            </p>
        </div>

        <button
            onclick="window.print()"
            class="btn btn-outline-success no-imprimir">
            Imprimir / Guardar PDF
        </button>

    </div>

    <!-- Filtro por mes -->
    <div class="card shadow-sm mb-4 no-imprimir">
        <div class="card-body">

            <form method="GET"
                  class="row g-3 align-items-end">

                <div class="col-md-5">
                    <label class="form-label">
                        Seleccionar mes
                    </label>

                    <input
                        type="month"
                        name="mes"
                        class="form-control"
                        value="<?= escapar($mes) ?>"
                        required>
                </div>

                <div class="col-md-3">
                    <button
                        class="btn btn-success w-100">
                        Consultar reporte
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">
                        Ingresos del mes
                    </p>
                    <h3 class="text-success">
                        <?= formatoDineroReporte($ingresos) ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">
                        Gastos del mes
                    </p>
                    <h3 class="text-danger">
                        <?= formatoDineroReporte($gastos) ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-1">
                        Balance del mes
                    </p>
                    <h3>
                        <?= formatoDineroReporte($balance) ?>
                    </h3>
                </div>
            </div>
        </div>

    </div>

    <!-- Gráficas -->
    <div class="row g-4 mb-4">

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">

                    <h5>Ingresos vs. Gastos</h5>

                    <div class="grafica-contenedor">
                        <canvas id="graficaBalance"></canvas>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">

                    <h5>Gastos por categoría</h5>

                    <?php if (!$gastosCategorias): ?>
                        <p class="text-muted">
                            No hay gastos registrados
                            durante este mes.
                        </p>
                    <?php else: ?>
                        <div class="grafica-contenedor">
                            <canvas id="graficaCategorias"></canvas>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

    <!-- Tabla de movimientos -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h5>Detalle de movimientos</h5>

            <p class="text-muted">
                Total de movimientos:
                <?= $totalMovimientos ?>
            </p>

            <div class="table-responsive">

                <table class="table table-striped">

                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($movimientos as $m): ?>
                            <tr>

                                <td>
                                    <?= escapar($m['fecha']) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        ucfirst($m['tipo'])
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar($m['categoria']) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        $m['descripcion'] ?? ''
                                    ) ?>
                                </td>

                                <td class="<?=
                                    $m['tipo'] === 'ingreso'
                                        ? 'text-success'
                                        : 'text-danger'
                                ?>">
                                    <?= formatoDineroReporte(
                                        $m['monto']
                                    ) ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$movimientos): ?>
                            <tr>
                                <td colspan="5"
                                    class="text-center text-muted">
                                    No hay movimientos
                                    en este periodo.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>

            </div>
        </div>
    </div>

</main>

<script>
const ingresos = <?= json_encode($ingresos) ?>;
const gastos = <?= json_encode($gastos) ?>;

const etiquetas = <?= json_encode(
    $etiquetas,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const valores = <?= json_encode($valores) ?>;

const formatoMoneda = valor =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(valor);

// Gráfica de ingresos y gastos.
new Chart(
    document.getElementById('graficaBalance'),
    {
        type: 'bar',

        data: {
            labels: ['Ingresos', 'Gastos'],

            datasets: [{
                label: 'Cantidad en MXN',
                data: [ingresos, gastos],
                backgroundColor: [
                    '#198754',
                    '#dc3545'
                ],
                borderRadius: 8
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            scales: {
                y: {
                    beginAtZero: true
                }
            },

            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: context =>
                            formatoMoneda(context.parsed.y)
                    }
                }
            }
        }
    }
);

// Gráfica de gastos por categoría.
if (etiquetas.length > 0) {
    new Chart(
        document.getElementById('graficaCategorias'),
        {
            type: 'doughnut',

            data: {
                labels: etiquetas,

                datasets: [{
                    data: valores,
                    backgroundColor: [
                        '#198754',
                        '#0d6efd',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1',
                        '#20c997',
                        '#fd7e14',
                        '#6610f2'
                    ]
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context =>
                                context.label + ': ' +
                                formatoMoneda(context.parsed)
                        }
                    }
                }
            }
        }
    );
}
</script>

</body>
</html>
