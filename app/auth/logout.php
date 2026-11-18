
<?php
require_once __DIR__ . '/../../config/sesion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

if (!validarCsrf()) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Vaciar la información de la sesión.
$_SESSION = [];

// Eliminar la cookie de sesión.
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parametros['path'],
        'domain' => $parametros['domain'],
        'secure' => $parametros['secure'],
        'httponly' => $parametros['httponly'],
        'samesite' => $parametros['samesite']
    ]);
}

// Destruir la sesión.
session_destroy();

header('Location: login.php');
exit;
