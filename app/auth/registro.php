
<?php
require_once __DIR__ . '/../../config/sesion.php';
require_once __DIR__ . '/../../config/database.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ../../dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrf()) {
        $error = 'Solicitud no válida.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if (
            strlen($nombre) < 3 ||
            strlen($nombre) > 100 ||
            !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
            strlen($correo) > 150 ||
            strlen($password) < 8 ||
            strlen($password) > 72
        ) {
            $error = 'Revisa los datos. La contraseña debe tener entre 8 y 72 caracteres.';
        } else {
            $consulta = $conexion->prepare(
                'SELECT id FROM usuarios WHERE correo = ?'
            );
            $consulta->execute([$correo]);

            if ($consulta->fetch()) {
                $error = 'Este correo ya está registrado.';
            } else {
                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $consulta = $conexion->prepare(
                    'INSERT INTO usuarios
                    (nombre, correo, password)
                    VALUES (?, ?, ?)'
                );

                $consulta->execute([
                    $nombre,
                    $correo,
                    $hash
                ]);

                header('Location: login.php?registro=ok');
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Registro - MiControl Financiero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width:450px;">
    <div class="card shadow p-4">
        <h2 class="text-center">Crear cuenta</h2>
        <p class="text-center text-muted">MiControl Financiero</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= escapar($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= escapar($_SESSION['csrf_token']) ?>">

            <label class="form-label">Nombre</label>
            <input type="text" name="nombre"
                   class="form-control mb-3"
                   maxlength="100" required>

            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo"
                   class="form-control mb-3"
                   maxlength="150" required>

            <label class="form-label">Contraseña</label>
            <input type="password" name="password"
                   class="form-control mb-3"
                   minlength="8" maxlength="72" required>

            <button class="btn btn-success w-100">
                Registrarme
            </button>
        </form>

        <p class="text-center mt-3">
            ¿Ya tienes cuenta?
            <a href="login.php">Iniciar sesión</a>
        </p>
    </div>
</div>
</body>
</html>
