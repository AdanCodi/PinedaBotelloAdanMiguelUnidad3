
<?php
require_once __DIR__ . '/../../config/sesion.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

if (!validarCsrf()) {
    http_response_code(403);
    exit('Solicitud no válida');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$usuarioId = (int) $_SESSION['usuario_id'];

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Movimiento no válido');
}

// Solo elimina movimientos del usuario autenticado.
$consulta = $conexion->prepare(
    "DELETE FROM movimientos
     WHERE id = ? AND usuario_id = ?"
);

$consulta->execute([$id, $usuarioId]);

header('Location: ../../dashboard.php?eliminado=1');
exit;
