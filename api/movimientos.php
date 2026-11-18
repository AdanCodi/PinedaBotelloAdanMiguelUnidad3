
<?php
require_once __DIR__ . '/../config/sesion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function responder($codigo, $datos) {
    http_response_code($codigo);
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

// Solamente aceptar GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    responder(405, [
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
}

// Verificar autenticación.
if (!isset($_SESSION['usuario_id'])) {
    responder(401, [
        'success' => false,
        'message' => 'Debes iniciar sesión.'
    ]);
}

// Validar parámetro límite.
$limite = filter_var(
    $_GET['limite'] ?? '10',
    FILTER_VALIDATE_INT
);

if ($limite === false || $limite < 1 || $limite > 100) {
    responder(400, [
        'success' => false,
        'message' => 'El límite debe estar entre 1 y 100.'
    ]);
}

$usuarioId = (int) $_SESSION['usuario_id'];

try {
    require_once __DIR__ . '/../config/database.php';

    $consulta = $conexion->prepare(
        "SELECT
            m.id,
            m.tipo,
            m.monto,
            m.descripcion,
            m.fecha,
            c.nombre AS categoria
         FROM movimientos m
         INNER JOIN categorias c
            ON c.id = m.categoria_id
            AND c.usuario_id = m.usuario_id
         WHERE m.usuario_id = ?
         ORDER BY m.fecha DESC, m.id DESC
         LIMIT $limite"
    );

    $consulta->execute([$usuarioId]);
    $movimientos = $consulta->fetchAll();

    responder(200, [
        'success' => true,
        'total' => count($movimientos),
        'data' => $movimientos
    ]);

} catch (Throwable $e) {
    error_log('Error en API movimientos: ' . $e->getMessage());

    responder(500, [
        'success' => false,
        'message' => 'Error interno del servidor.'
    ]);
}
