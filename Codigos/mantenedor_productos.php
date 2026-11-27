<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre_prod = trim($_POST['nombre_prod'] ?? '');
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);

        if (empty($nombre_prod) || $precio === false || $precio <= 0) {
            $error_ocurrido = true;
            $mensaje = "Error: Nombre y Precio (debe ser > 0) son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_PRODUCTO(:p_nombre, :p_precio); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre_prod);
            oci_bind_by_name($stmt, ':p_precio', $precio);

            if (@oci_execute($stmt)) {
                $mensaje = "Producto creado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
        $nombre_prod = trim($_POST['nombre_prod'] ?? '');
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);

        if ($id_producto === false || empty($nombre_prod) || $precio === false || $precio <= 0) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos invalidos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_PRODUCTO(:p_id, :p_nombre, :p_precio); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_producto);
            oci_bind_by_name($stmt, ':p_nombre', $nombre_prod);
            oci_bind_by_name($stmt, ':p_precio', $precio);

            if (@oci_execute($stmt)) {
                $mensaje = "Producto actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_producto = filter_input(INPUT_POST, 'id_producto_eliminar', FILTER_VALIDATE_INT);

        if ($id_producto === false) {
            $error_ocurrido = true;
            $mensaje = "Error: ID de producto invalido.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_PRODUCTO(:p_id); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_producto);

            if (@oci_execute($stmt)) {
                $mensaje = "Producto eliminado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                if (strpos($e['message'], 'integrity constraint') !== false) {
                    $mensaje = "Error: No se puede eliminar el producto porque esta asociado a un pedido o embotellado.";
                } else {
                    $mensaje = "Error al eliminar: " . $e['message'];
                }
            }
            oci_free_statement($stmt);
        }
    }

    header('Location: mantenedor_productos.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listado
$productos_list = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PRODUCTOS');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($productos_list as $item) {
        if ($item['ID_PRODUCTO'] == $_GET['editar']) {
            $editando = $item;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor de Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Productos</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gestion de Productos</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <?php echo $editando ? 'Editar Producto #' . $editando['ID_PRODUCTO'] : 'Nuevo Producto Final'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="mantenedor_productos.php">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_producto" value="<?php echo $editando['ID_PRODUCTO']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nombre del Producto</label>
                                <input type="text" name="nombre_prod" class="form-control" required placeholder="Ej: Merlot Reserva 2022"
                                       value="<?php echo htmlspecialchars($editando['NOMBRE_PROD'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Precio de Venta ($)</label>
                                <input type="number" step="0.01" name="precio" class="form-control" required min="100"
                                       value="<?php echo htmlspecialchars($editando['PRECIO'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Producto' : 'Crear Producto'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_productos.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Productos</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Stock (Uds)</th>
                                    <th>Precio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productos_list)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No hay productos registrados.</td></tr>
                                <?php else: ?>
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
                                            <a href="mantenedor_productos.php?editar=<?php echo $row['ID_PRODUCTO']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar producto?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_producto_eliminar" value="<?php echo $row['ID_PRODUCTO']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
                                        </td>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('table.table').each(function () {
                if ($(this).find('tbody td[colspan]').length === 0) {
                    $(this).DataTable({
                        pageLength: 10,
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' }
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php oci_close($conn); ?>
