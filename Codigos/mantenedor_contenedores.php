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

    // Crear
    if ($accion === 'crear') {
        $material = $_POST['material'] ?? '';
        $capacidad = filter_input(INPUT_POST, 'capacidad', FILTER_VALIDATE_FLOAT); 

        if (empty($material) || $capacidad === false || $capacidad <= 0) {
            $error_ocurrido = true;
            $mensaje = "Error: Material y Capacidad (debe ser > 0) son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_CONTENEDOR(:p_mat, :p_cap); END;';
            $stmt = oci_parse($conn, $sql);
            
            oci_bind_by_name($stmt, ':p_mat', $material);
            oci_bind_by_name($stmt, ':p_cap', $capacidad);
            
            if (@oci_execute($stmt)) {
                $mensaje = "Contenedor creado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    // Actualizar
    if ($accion === 'editar') {
        $id_contenedor = filter_input(INPUT_POST, 'id_contenedor', FILTER_VALIDATE_INT);
        $material = $_POST['material'] ?? '';
        $capacidad = filter_input(INPUT_POST, 'capacidad', FILTER_VALIDATE_FLOAT); 
        $nro_usos = filter_input(INPUT_POST, 'nro_usos', FILTER_VALIDATE_INT); 

        if ($id_contenedor === false || empty($material) || $capacidad === false || $capacidad <= 0 || $nro_usos === false || $nro_usos < 0) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos de actualizacion invalidos o incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_ACTUALIZAR_CONTENEDOR(:p_id, :p_mat, :p_cap, :p_usos); END;';
            $stmt = oci_parse($conn, $sql);
            
            oci_bind_by_name($stmt, ':p_id', $id_contenedor);
            oci_bind_by_name($stmt, ':p_mat', $material);
            oci_bind_by_name($stmt, ':p_cap', $capacidad);
            oci_bind_by_name($stmt, ':p_usos', $nro_usos);

            if (@oci_execute($stmt)) {
                $mensaje = "Contenedor actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id_contenedor = filter_input(INPUT_POST, 'id_contenedor_eliminar', FILTER_VALIDATE_INT);

        if ($id_contenedor === false) {
            $error_ocurrido = true;
            $mensaje = "Error: ID de contenedor invalido.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_ELIMINAR_CONTENEDOR(:p_id); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_contenedor);

            if (@oci_execute($stmt)) {
                $mensaje = "Contenedor eliminado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                if (strpos($e['message'], 'integrity constraint') !== false) {
                    $mensaje = "Error: No se puede eliminar el contenedor porque esta siendo usado.";
                } else {
                    $mensaje = "Error al eliminar: " . $e['message'];
                }
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion) {
        header('Location: mantenedor_contenedores.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
        exit;
    }
}

// Listar contenedores
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

$cursor = oci_new_cursor($conn);
$sql_list = 'BEGIN CA_EA_ST_IY_SP_REPORTE_OCUPACION_BODEGA(:cursor); END;'; 
$stmt_list = oci_parse($conn, $sql_list);
oci_bind_by_name($stmt_list, ':cursor', $cursor, -1, OCI_B_CURSOR);
@oci_execute($stmt_list);
@oci_execute($cursor);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor Contenedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">
                Mantenedor de Contenedores
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver</a>
        
        <h2 class="mb-4">Gestión de Contenedores (CRUD)</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">Nuevo Contenedor</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">Material</label>
                                <select name="material" class="form-select" required>
                                    <option value="Acero Inoxidable">Acero Inoxidable</option>
                                    <option value="Roble Francés">Roble Francés</option>
                                    <option value="Roble Americano">Roble Americano</option>
                                    <option value="Hormigón">Hormigón</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Capacidad (Litros)</label>
                                <input type="number" step="0.01" name="capacidad" class="form-control" required min="1">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card border-info">
                    <div class="card-header bg-info text-dark">Listado Actual</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Material</th>
                                    <th>Capacidad (L)</th>
                                    <th>Usos</th>
                                    <th style="width: 150px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = oci_fetch_assoc($cursor)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['ID_CONTENEDOR']); ?></td>
                                    <td><?php echo htmlspecialchars($row['MATERIAL']); ?></td>
                                    <td><?php echo number_format($row['CAPACIDAD'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if(strpos(strtoupper($row['MATERIAL']), 'ROBLE') !== false): ?>
                                            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['NRO_USOS']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-warning me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditar"
                                            data-id="<?php echo $row['ID_CONTENEDOR']; ?>"
                                            data-material="<?php echo htmlspecialchars($row['MATERIAL']); ?>"
                                            data-capacidad="<?php echo $row['CAPACIDAD']; ?>"
                                            data-usos="<?php echo $row['NRO_USOS']; ?>"
                                        >
                                            Editar
                                        </button>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar el contenedor ID <?php echo htmlspecialchars($row['ID_CONTENEDOR']); ?>?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_contenedor_eliminar" value="<?php echo $row['ID_CONTENEDOR']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="modalEditarLabel">Editar Contenedor <span id="modal_id_contenedor_display"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id_contenedor" id="edit_id_contenedor">
                        
                        <div class="mb-3">
                            <label for="edit_material" class="form-label">Material:</label>
                            <select id="edit_material" name="material" class="form-select" required>
                                <option value="Acero Inoxidable">Acero Inoxidable</option>
                                <option value="Roble Francés">Roble Francés</option>
                                <option value="Roble Americano">Roble Americano</option>
                                <option value="Hormigón">Hormigón</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_capacidad" class="form-label">Capacidad (Litros):</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="edit_capacidad" name="capacidad" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nro_usos" class="form-label">Número de Usos:</label>
                            <input type="number" min="0" class="form-control" id="edit_nro_usos" name="nro_usos" required>
                            <div class="form-text">Solo editable si el material es Roble.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var modalEditar = document.getElementById('modalEditar');
    modalEditar.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var id = button.getAttribute('data-id');
      var material = button.getAttribute('data-material');
      var capacidad = button.getAttribute('data-capacidad');
      var usos = button.getAttribute('data-usos');
      
      var modalTitle = modalEditar.querySelector('#modal_id_contenedor_display');
      var inputId = modalEditar.querySelector('#edit_id_contenedor');
      var selectMaterial = modalEditar.querySelector('#edit_material');
      var inputCapacidad = modalEditar.querySelector('#edit_capacidad');
      var inputUsos = modalEditar.querySelector('#edit_nro_usos');

      modalTitle.textContent = id;
      inputId.value = id;
      selectMaterial.value = material;
      inputCapacidad.value = capacidad;
      inputUsos.value = usos;
    });
    </script>
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
<?php
oci_free_statement($stmt_list);
oci_free_statement($cursor);
oci_close($conn);
?>