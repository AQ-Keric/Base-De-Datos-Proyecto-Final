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
        $rut = trim($_POST['rut_proveedor'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');

        if (empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: El RUT y la Razon Social son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_PROVEEDOR(:p_rut, :p_razon); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);

            if (@oci_execute($stmt)) {
                $mensaje = "Proveedor '$razon' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear proveedor: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_prov = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
        $rut = trim($_POST['rut_proveedor'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');

        if ($id_prov === false || empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_PROVEEDOR(:p_id, :p_rut, :p_razon); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_prov);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);

            if (@oci_execute($stmt)) {
                $mensaje = "Proveedor ID $id_prov actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar proveedor: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_prov = filter_input(INPUT_POST, 'id_proveedor_eliminar', FILTER_VALIDATE_INT);
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_PROVEEDOR(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_prov);

        if (@oci_execute($stmt)) {
            $mensaje = "Proveedor ID $id_prov eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el proveedor porque tiene insumos asociados.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_proveedores.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listado
$proveedores = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PROVEEDORES');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($proveedores as $item) {
        if ($item['ID_PROVEEDOR'] == $_GET['editar']) {
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
    <title>Mantenedor de Proveedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Proveedores</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Proveedores</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <?php echo $editando ? 'Editar Proveedor #' . $editando['ID_PROVEEDOR'] : 'Nuevo Proveedor'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_proveedores.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_proveedor" value="<?php echo $editando['ID_PROVEEDOR']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">RUT Proveedor:</label>
                                <input type="text" class="form-control" name="rut_proveedor" placeholder="ej: 76543210-K" required
                                       value="<?php echo htmlspecialchars($editando['RUT_PROVEEDOR'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Razon Social:</label>
                                <input type="text" class="form-control" name="razon_social" placeholder="ej: Agricola del Valle S.A." required
                                       value="<?php echo htmlspecialchars($editando['RAZON_SOCIAL'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Proveedor' : 'Guardar Proveedor'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_proveedores.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Proveedores</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>RUT</th>
                                    <th>Razon Social</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($proveedores)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No hay proveedores registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($proveedores as $p): ?>
                                        <tr>
                                            <td><?php echo $p['ID_PROVEEDOR']; ?></td>
                                            <td><?php echo htmlspecialchars($p['RUT_PROVEEDOR']); ?></td>
                                            <td><?php echo htmlspecialchars($p['RAZON_SOCIAL']); ?></td>
                                            <td>
                                                <a href="mantenedor_proveedores.php?editar=<?php echo $p['ID_PROVEEDOR']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                                <form action="mantenedor_proveedores.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar proveedor?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_proveedor_eliminar" value="<?php echo $p['ID_PROVEEDOR']; ?>">
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
</body>
</html>
<?php oci_close($conn); ?>
