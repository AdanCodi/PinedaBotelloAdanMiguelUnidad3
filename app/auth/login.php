
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
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        $consulta = $conexion->prepare(
            'SELECT id, nombre, password
             FROM usuarios
             WHERE correo = ?'
        );
        $consulta->execute([$correo]);

        $usuario = $consulta->fetch();

        if (
            $usuario &&
            password_verify($password, $usuario['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];

            header('Location: ../../dashboard.php');
            exit;
        }

        $error = 'Correo o contraseña incorrectos.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width:450px;">
    <div class="card shadow p-4">
        <h2 class="text-center">Iniciar sesión</h2>
        <p class="text-center text-muted">MiControl Financiero</p>

        <?php if (isset($_GET['registro'])): ?>
            <div class="alert alert-success">
                Cuenta creada correctamente.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= escapar($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= escapar($_SESSION['csrf_token']) ?>">

            <label class="form-label">Correo</label>
            <input type="email" name="correo"
                   class="form-control mb-3" required>

            <label class="form-label">Contraseña</label>
            <input type="password" name="password"
                   class="form-control mb-3" required>

            <button class="btn btn-success w-100">
                Ingresar
            </button>
        </form>

        <p class="text-center mt-3">
            ¿No tienes cuenta?
            <a href="registro.php">Registrarse</a>
        </p>
    </div>
</div>
</body>
</html>
