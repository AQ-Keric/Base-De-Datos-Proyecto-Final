<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;
$clientes = [];
$productos = [];
$pedidos = [];

// Crear pedido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $rut_cliente = trim($_POST['rut_cliente'] ?? '');
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);

    if (empty($rut_cliente) || $id_producto === false || $cantidad === false || $cantidad <= 0) {
        $error_ocurrido = true;
        $mensaje = "Error: Debe seleccionar un cliente, un producto y una cantidad valida mayor a 0.";
    } else {
        $sql = 'BEGIN CA_EA_ST_IY_SP_GESTION_CREAR_PEDIDO(:p_rut, :p_producto, :p_cant); END;';
        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ':p_rut', $rut_cliente);
        oci_bind_by_name($stmt, ':p_producto', $id_producto);
        oci_bind_by_name($stmt, ':p_cant', $cantidad);

        if (@oci_execute($stmt)) {
            $mensaje = "Pedido creado exitosamente para el cliente RUT: $rut_cliente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            $mensaje = "Error al crear pedido: " . $e['message'];
        }
        oci_free_statement($stmt);
    }

    header('Location: gestor_pedido.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Cargar datos
$sql_clientes = "SELECT rut_cliente, razon_social FROM CA_EA_ST_IY_CLIENTE ORDER BY razon_social";
$stmt_cli = oci_parse($conn, $sql_clientes);
if (@oci_execute($stmt_cli)) {
    while ($row = oci_fetch_assoc($stmt_cli)) {
        $clientes[] = $row;
    }
}
oci_free_statement($stmt_cli);

$sql_prod = "SELECT id_producto, nombre_prod, stock, precio FROM CA_EA_ST_IY_PRODUCTOS ORDER BY nombre_prod";
$stmt_prod = oci_parse($conn, $sql_prod);
if (@oci_execute($stmt_prod)) {
    while ($row = oci_fetch_assoc($stmt_prod)) {
        $productos[] = $row;
    }
}
oci_free_statement($stmt_prod);

$sql_pedidos = "SELECT p.id_pedido, c.rut_cliente, c.razon_social, p.fecha_pedido, p.estado_pedido,
                       pr.nombre_prod, dp.cantidad, pr.precio, (dp.cantidad * pr.precio) AS total
                FROM CA_EA_ST_IY_PEDIDO_VENTA p
                JOIN CA_EA_ST_IY_CLIENTE c ON p.id_cliente = c.id_cliente
                JOIN CA_EA_ST_IY_DETALLE_PEDIDO_VENTA dp ON p.id_pedido = dp.id_pedido
                JOIN CA_EA_ST_IY_PRODUCTOS pr ON dp.id_producto = pr.id_producto
                ORDER BY p.id_pedido DESC";
$stmt_ped = oci_parse($conn, $sql_pedidos);
if (@oci_execute($stmt_ped)) {
    while ($row = oci_fetch_assoc($stmt_ped)) {
        $pedidos[] = $row;
    }
}
oci_free_statement($stmt_ped);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de Pedidos de Venta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">
                Gestion de Pedidos
            </span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gestion de Pedidos de Venta</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Nuevo Pedido de Venta</h5>
                    </div>
                    <div class="card-body">
                        <form action="gestor_pedido.php" method="POST">
                            <div class="mb-3">
                                <label for="rut_cliente" class="form-label">Cliente:</label>
                                <select class="form-select" id="rut_cliente" name="rut_cliente" required>
                                    <option value="">Seleccione un Cliente...</option>
                                    <?php foreach ($clientes as $cli): ?>
                                        <option value="<?php echo htmlspecialchars($cli['RUT_CLIENTE']); ?>">
                                            <?php echo htmlspecialchars($cli['RAZON_SOCIAL'] . ' (' . $cli['RUT_CLIENTE'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="id_producto" class="form-label">Producto:</label>
                                <select class="form-select" id="id_producto" name="id_producto" required>
                                    <option value="">Seleccione Producto...</option>
                                    <?php foreach ($productos as $prod): ?>
                                        <option value="<?php echo $prod['ID_PRODUCTO']; ?>">
                                            <?php echo htmlspecialchars($prod['NOMBRE_PROD']) . " (Stock: " . $prod['STOCK'] . ", $" . number_format($prod['PRECIO'], 0, ',', '.') . ")"; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="cantidad" class="form-label">Cantidad (Unidades):</label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Registrar Pedido</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Historial de Pedidos Registrados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Cant.</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pedidos)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No hay pedidos registrados aún.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pedidos as $p): ?>
                                            <tr>
                                                <td>#<?php echo $p['ID_PEDIDO']; ?></td>
                                                <td><?php echo htmlspecialchars($p['RAZON_SOCIAL']); ?></td>
                                                <td><?php echo htmlspecialchars($p['FECHA_PEDIDO']); ?></td>
                                                <td><?php echo htmlspecialchars($p['NOMBRE_PROD']); ?></td>
                                                <td><?php echo number_format($p['CANTIDAD'], 0, ',', '.'); ?></td>
                                                <td>$<?php echo number_format($p['TOTAL'], 0, ',', '.'); ?></td>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($p['ESTADO_PEDIDO']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
