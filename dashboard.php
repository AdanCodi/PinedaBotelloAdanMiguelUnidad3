
<?php
require_once __DIR__ . '/config/sesion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: app/auth/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>MiControl Financiero</title>
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
            class="btn btn-outline-light">
                Cerrar sesión
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Bienvenido,
            <?= escapar($_SESSION['usuario_nombre']) ?>
        </h2>

        <p>Este es tu panel de control financiero.</p>

        <div class="alert alert-success">
            Has iniciado sesión correctamente.
        </div>
    </div>
</body>
</html>
