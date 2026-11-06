
<?php

$host = "localhost";
$puerto = "3306";
$nombreBD = "micontrol_financiero";
$usuario = "root";
$contrasena = "";

try {
    $conexion = new PDO(
        "mysql:host=$host;port=$puerto;dbname=$nombreBD;charset=utf8mb4",
        $usuario,
        $contrasena
    );

    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $conexion->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    throw new RuntimeException(
        "No se pudo conectar con la base de datos."
    );
}
