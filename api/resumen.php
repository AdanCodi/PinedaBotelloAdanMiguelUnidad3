
<?php
require_once __DIR__ . '/../config/sesion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function responderResumen($codigo, $datos) {
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    responderResumen(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

if (!isset($_SESSION['usuario_id'])) {
    responderResumen(401, [
        'success' => false,
        'message' => 'Debes iniciar sesión.'
    ]);
}

$usuarioId = (int) $_SESSION['usuario_id'];

try {
    require_once __DIR__ . '/../config/database.php';

    $consulta = $conexion->prepare(
        "SELECT
            COALESCE(SUM(
                CASE WHEN tipo = 'ingreso'
                THEN monto ELSE 0 END
            ), 0) AS ingresos,

            COALESCE(SUM(
                CASE WHEN tipo = 'gasto'
                THEN monto ELSE 0 END
            ), 0) AS gastos

         FROM movimientos
         WHERE usuario_id = ?"
    );

    $consulta->execute([$usuarioId]);
    $resultado = $consulta->fetch();

    $ingresos = (float) $resultado['ingresos'];
    $gastos = (float) $resultado['gastos'];
    $saldo = $ingresos - $gastos;

    responderResumen(200, [
        'success' => true,
        'data' => [
            'ingresos' => round($ingresos, 2),
            'gastos' => round($gastos, 2),
            'saldo' => round($saldo, 2),
            'moneda' => 'MXN'
        ]
    ]);

} catch (Throwable $e) {
    error_log('Error en API resumen: ' . $e->getMessage());

    responderResumen(500, [
        'success' => false,
        'message' => 'Error interno del servidor.'
    ]);
}
