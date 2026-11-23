<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// Proveedores
$proveedores = [];
$sql_prov = "SELECT ID_PROVEEDOR, RAZON_SOCIAL FROM CA_EA_ST_IY_PROVEEDOR ORDER BY RAZON_SOCIAL";
$stmt_prov = oci_parse($conn, $sql_prov);
if (@oci_execute($stmt_prov)) { 
    while ($row = oci_fetch_assoc($stmt_prov)) {
        $proveedores[] = $row;
    }
} 

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';
    
    // Crear
    if ($accion === 'crear') {
        $id_proveedor = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
        $nombre_insumo = $_POST['nombre_insumo'];
        $stock_min = filter_input(INPUT_POST, 'stock_min', FILTER_VALIDATE_INT);
        $unidad_medida = $_POST['unidad_medida'];
        
        $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_CREAR_INSUMO(:p_prov, :p_nom, :p_min, :p_uni); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_prov', $id_proveedor);
        oci_bind_by_name($stmt, ':p_nom', $nombre_insumo);
        oci_bind_by_name($stmt, ':p_min', $stock_min);
        oci_bind_by_name($stmt, ':p_uni', $unidad_medida); 
        
        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Insumo '$nombre_insumo' registrado correctamente.";
        } else {
            $e = oci_error($stmt);
            $mensaje = "Error al crear insumo: " . $e['message'];
            $error_ocurrido = true;
        }
        @oci_free_statement($stmt);
    }
    
    // Actualizar stock
    if ($accion === 'actualizar_stock') {
        $id_insumo = filter_input(INPUT_POST, 'edit_id_insumo', FILTER_VALIDATE_INT);
        $cantidad = filter_input(INPUT_POST, 'cantidad_movimiento', FILTER_VALIDATE_INT);

        $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_UPDATE_STOCK(:p_id, :p_cant); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_id', $id_insumo);
        oci_bind_by_name($stmt, ':p_cant', $cantidad);
        
        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Stock del insumo ID $id_insumo actualizado. Nuevo Stock: $cantidad.";
        } else {
            $e = oci_error($stmt);
            $mensaje = "Error al actualizar stock: " . $e['message'];
            $error_ocurrido = true;
        }
        @oci_free_statement($stmt);
    }
    
    // Eliminar
    if ($accion === 'eliminar') {
        $id_insumo = filter_input(INPUT_POST, 'id_insumo_eliminar', FILTER_VALIDATE_INT);
        
        $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_DEL_INSUMO(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_insumo);
        
        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Insumo ID $id_insumo eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            if ($e['code'] == 2292) {
                 $mensaje = "Error: El insumo ID $id_insumo no puede ser eliminado porque tiene aplicaciones historicas asociadas.";
            } else {
                 $mensaje = "Error al eliminar insumo: " . $e['message'];
            }
            $error_ocurrido = true;
        }
        @oci_free_statement($stmt);
    }
}

// Listado
$sql_list = "SELECT 
                I.ID_INSUMO, 
                I.NOMBRE_INSUMO, 
                I.STOCK_ACTUAL, 
                I.STOCK_MIN, 
                I.UNIDAD_MEDIDA,
                P.RAZON_SOCIAL AS PROVEEDOR
             FROM CA_EA_ST_IY_INSUMOS I
             LEFT JOIN CA_EA_ST_IY_PROVEEDOR P ON I.ID_PROVEEDOR = P.ID_PROVEEDOR
             ORDER BY I.NOMBRE_INSUMO";
             
$stmt_list = oci_parse($conn, $sql_list);
$list_execution_exitosa = @oci_execute($stmt_list);

if (!$list_execution_exitosa) {
    $e = oci_error($stmt_list);
    if ($e) {
        $mensaje = "Error al cargar la lista de insumos: " . $e['message'];
        $error_ocurrido = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor de Insumos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header-custom { background-color: #3C6382; color: white; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gestión de Insumos y Stock</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header card-header-custom">Registrar Nuevo Insumo</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">
                            
                            <div class="mb-3">
                                <label for="nombre_insumo" class="form-label">Nombre Insumo</label>
                                <input type="text" class="form-control" id="nombre_insumo" name="nombre_insumo" required>
                            </div>
                            <div class="mb-3">
                                <label for="unidad_medida" class="form-label">Unidad de Medida</label>
                                <input type="text" class="form-control" id="unidad_medida" name="unidad_medida" required placeholder="Ej: Kg, Litros, Unidades">
                            </div>
                            <div class="mb-3">
                                <label for="stock_min" class="form-label">Stock Mínimo (Alerta)</label>
                                <input type="number" class="form-control" id="stock_min" name="stock_min" required min="0">
                            </div>
                            <div class="mb-3">
                                <label for="id_proveedor" class="form-label">Proveedor</label>
                                <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                                    <option value="">Seleccione...</option>
                                    <?php if (!empty($proveedores)): ?>
                                        <?php foreach ($proveedores as $prov): ?>
                                            <option value="<?php echo $prov['ID_PROVEEDOR']; ?>"><?php echo htmlspecialchars($prov['RAZON_SOCIAL']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">(Error: No se cargaron Proveedores)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Insumo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header card-header-custom">Inventario de Insumos</div>
                    <div class="card-body">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Insumo</th>
                                    <th>Stock Actual</th>
                                    <th>Mín.</th>
                                    <th>Proveedor</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($list_execution_exitosa): 
                                    while ($row = oci_fetch_assoc($stmt_list)): 
                                        $alerta_stock = $row['STOCK_ACTUAL'] <= $row['STOCK_MIN'];
                                ?>
                                    <tr class="<?php echo $alerta_stock ? 'table-danger' : ''; ?>">
                                        <td><?php echo $row['ID_INSUMO']; ?></td>
                                        <td><?php echo $row['NOMBRE_INSUMO']; ?></td>
                                        <td>
                                            <?php echo number_format($row['STOCK_ACTUAL'], 0, ',', '.'); ?> <?php echo $row['UNIDAD_MEDIDA']; ?>
                                            <?php if ($alerta_stock): ?>
                                                <span class="badge bg-danger ms-2">Stock Critico</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($row['STOCK_MIN'], 0, ',', '.'); ?></td>
                                        <td><?php echo $row['PROVEEDOR'] ?? 'N/A'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info me-2 text-white" data-bs-toggle="modal" 
                                                    data-bs-target="#modalActualizarStock" 
                                                    data-id="<?php echo $row['ID_INSUMO']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($row['NOMBRE_INSUMO']); ?>"
                                                    data-stock="<?php echo $row['STOCK_ACTUAL']; ?>"
                                                    data-unidad="<?php echo htmlspecialchars($row['UNIDAD_MEDIDA']); ?>">
                                                Stock
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar el insumo <?php echo htmlspecialchars($row['NOMBRE_INSUMO']); ?>?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_insumo_eliminar" value="<?php echo $row['ID_INSUMO']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-danger">No se pudo cargar la lista de insumos. Revise el error de Oracle.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalActualizarStock" tabindex="-1" aria-labelledby="modalActualizarStockLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalActualizarStockLabel">Actualizar Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="actualizar_stock">
                        <input type="hidden" id="edit_id_insumo" name="edit_id_insumo">
                        
                        <p>Insumo: <strong id="modal_insumo_nombre"></strong></p>
                        <p>Stock Actual: <span id="modal_stock_actual" class="fw-bold"></span></p>

                        <div class="mb-3">
                            <label for="cantidad_movimiento" class="form-label">Nuevo Stock Total</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="cantidad_movimiento" name="cantidad_movimiento" required min="0" step="1">
                                <span class="input-group-text" id="modal_unidad_medida"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-info text-white">Guardar Nuevo Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var modalStock = document.getElementById('modalActualizarStock');
    modalStock.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var id = button.getAttribute('data-id');
      var nombre = button.getAttribute('data-nombre');
      var stock = button.getAttribute('data-stock');
      var unidad = button.getAttribute('data-unidad');
      
      var inputId = modalStock.querySelector('#edit_id_insumo');
      var displayNombre = modalStock.querySelector('#modal_insumo_nombre');
      var displayStock = modalStock.querySelector('#modal_stock_actual');
      var inputCantidad = modalStock.querySelector('#cantidad_movimiento');
      var displayUnidad = modalStock.querySelector('#modal_unidad_medida');

      displayNombre.textContent = nombre;
      displayStock.textContent = stock + ' ' + unidad;
      displayUnidad.textContent = unidad;
      inputId.value = id;
      inputCantidad.value = stock; 
    });
    </script>
</body>
</html>
<?php

if (isset($stmt_list) && $stmt_list) {
    @oci_free_statement($stmt_list);
}
if (isset($stmt_prov) && $stmt_prov) {
    @oci_free_statement($stmt_prov);
}
oci_close($conn);
?>