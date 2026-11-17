<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    // header("Location: index.php"); 
    // exit;
}
require 'db.php'; 

$mensaje = "";
$error_ocurrido = false;

// --- LÓGICA DE CREATE, UPDATE, DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $accion = $_POST['accion'] ?? '';

    // ------------------- CREAR PRODUCTO -------------------
    if ($accion === 'crear') {
        $nombre_prod = $_POST['nombre_prod'] ?? '';
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT); 

        if (empty($nombre_prod) || $precio === false || $precio <= 0) {
            $error_ocurrido = true;
            $mensaje = "Error: Nombre y Precio (debe ser > 0) son obligatorios.";
        } else {

            $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_CREAR_PRODUCTO(:p_nombre, :p_precio); END;';
            $stmt = oci_parse($conn, $sql);
            
            oci_bind_by_name($stmt, ':p_nombre', $nombre_prod);
            oci_bind_by_name($stmt, ':p_precio', $precio);
            
            if (@oci_execute($stmt)) {
                $mensaje = "Producto Creado con Éxito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al Crear: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    // ------------------- ELIMINAR PRODUCTO  -------------------
    if ($accion === 'eliminar') {
        $id_producto = filter_input(INPUT_POST, 'id_producto_eliminar', FILTER_VALIDATE_INT);

        if ($id_producto === false) {
            $error_ocurrido = true;
            $mensaje = "Error: ID de Producto a eliminar inválido.";
        } else {
            $sql = 'DELETE FROM CA_EA_ST_IY_PRODUCTOS WHERE id_producto = :p_id';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_producto);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "🗑️ Producto Eliminado con Éxito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                if (strpos($e['message'], 'integrity constraint') !== false) {
                    $mensaje = "Error: No se puede eliminar el producto porque está asociado a un pedido o embotellado.";
                } else {
                    $mensaje = "Error al Eliminar: " . $e['message'];
                }
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }
    
    if ($accion) {
        header('Location: mantenedor_productos.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
        exit;
    }
}

// --- LÓGICA DE LISTAR (READ)
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

$productos_list = [];
$sql_list = 'SELECT id_producto, nombre_prod, stock, precio FROM CA_EA_ST_IY_PRODUCTOS ORDER BY id_producto DESC'; 
$stmt_list = oci_parse($conn, $sql_list);
@oci_execute($stmt_list);

while ($row = oci_fetch_assoc($stmt_list)) {
    $productos_list[] = $row;
}
oci_free_statement($stmt_list);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
        .table-scroll { max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">
                Mantenedor de Productos Finales
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver</a>
        
        <h2 class="mb-4">Gestión de Productos para Venta (CRUD Básico)</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">Nuevo Producto Final</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label for="nombre_prod" class="form-label">Nombre del Producto</label>
                                <input type="text" id="nombre_prod" name="nombre_prod" class="form-control" required placeholder="Ej: Merlot Reserva 2022">
                            </div>
                            <div class="mb-3">
                                <label for="precio" class="form-label">Precio de Venta ($)</label>
                                <input type="number" step="0.01" id="precio" name="precio" class="form-control" required min="100">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Crear Producto</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card border-info">
                    <div class="card-header bg-info text-dark">Listado de Productos</div>
                    <div class="card-body">
                        <div class="table-scroll">
                            <table class="table table-striped table-hover table-sm">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Stock (Uds)</th>
                                        <th>Precio</th>
                                        <th style="width: 120px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_list as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ID_PRODUCTO']); ?></td>
                                        <td><?php echo htmlspecialchars($row['NOMBRE_PROD']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ((float)$row['STOCK'] > 0 ? 'success' : 'danger'); ?>">
                                                <?php echo number_format((float)$row['STOCK'], 0, '', '.'); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format((float)$row['PRECIO'], 0, ',', '.'); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar el Producto ID <?php echo htmlspecialchars($row['ID_PRODUCTO']); ?>? Esto fallará si está en un pedido.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_producto_eliminar" value="<?php echo $row['ID_PRODUCTO']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
<?php
oci_close($conn);
?>