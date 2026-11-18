
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
    <title>Tipo de cambio - MiControl Financiero</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-success">
    <div class="container">
        <span class="navbar-brand">MiControl Financiero</span>

        <a href="dashboard.php"
           class="btn btn-outline-light btn-sm">
            Volver al dashboard
        </a>
    </div>
</nav>

<main class="container py-5" style="max-width:650px;">

    <div class="card shadow-sm">
        <div class="card-body p-4">

            <h3>Conversor de divisas</h3>
            <p class="text-muted">
                Consulta el tipo de cambio de referencia USD/MXN.
            </p>

            <label class="form-label">Cantidad en dólares</label>

            <input type="number"
                   id="cantidad"
                   class="form-control"
                   value="100"
                   min="0"
                   step="0.01">

            <button id="consultar"
                    class="btn btn-success mt-3">
                Consultar tipo de cambio
            </button>

            <div id="resultado"
                 class="alert alert-info mt-4">
                Presiona el botón para consultar.
            </div>

        </div>
    </div>

</main>

<script>
const boton = document.getElementById('consultar');
const resultado = document.getElementById('resultado');

boton.addEventListener('click', async () => {
    const cantidad = Number(
        document.getElementById('cantidad').value
    );

    if (!Number.isFinite(cantidad) || cantidad < 0) {
        resultado.textContent = 'Ingresa una cantidad válida.';
        return;
    }

    resultado.textContent = 'Consultando API externa...';

    try {
        const respuesta = await fetch(
            'https://api.frankfurter.dev/v1/latest?base=USD&symbols=MXN'
        );

        if (!respuesta.ok) {
            throw new Error('No se pudo consultar el servicio.');
        }

        const datos = await respuesta.json();
        const tasa = Number(datos.rates?.MXN);

        if (!Number.isFinite(tasa) || tasa <= 0) {
            throw new Error('El tipo de cambio no está disponible.');
        }

        const total = cantidad * tasa;

        const formato = numero =>
            new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            }).format(numero);

        resultado.textContent =
            'Fecha del tipo de cambio: ' + datos.date +
            '\n1 USD = ' + formato(tasa) +
            '\n' + cantidad + ' USD = ' + formato(total);

        resultado.style.whiteSpace = 'pre-line';

    } catch (error) {
        resultado.textContent =
            'Error al consultar el tipo de cambio: ' +
            error.message;
    }
});
</script>

</body>
</html>
