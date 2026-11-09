<?php
require_once __DIR__ . '/config/sesion.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: app/auth/login.php');
}

exit;
