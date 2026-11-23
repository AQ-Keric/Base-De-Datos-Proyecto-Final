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
        $rut = trim($_POST['rut_proveedor'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');

        if (empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: El RUT y la Razón Social son obligatorios.";
        } else {
            $sql = 'INSERT INTO CA_EA_ST_IY_PROVEEDOR (rut_proveedor, razon_social) VALUES (:p_rut, :p_razon)';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "Proveedor '$razon' registrado con éxito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear proveedor: " . $e['message'];
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id_prov = filter_input(INPUT_POST, 'id_proveedor_eliminar', FILTER_VALIDATE_INT);
        $sql = 'DELETE FROM CA_EA_ST_IY_PROVEEDOR WHERE id_proveedor = :p_id';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_prov);

        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Proveedor ID $id_prov eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el proveedor porque tiene insumos asociados.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_proveedores.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Listado
$proveedores = [];
$sql_list = "SELECT id_proveedor, rut_proveedor, razon_social FROM CA_EA_ST_IY_PROVEEDOR ORDER BY id_proveedor DESC";
$stmt_list = oci_parse($conn, $sql_list);
if (@oci_execute($stmt_list)) {
    while ($row = oci_fetch_assoc($stmt_list)) {
        $proveedores[] = $row;
    }
}
oci_free_statement($stmt_list);
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
                        <h5 class="card-title mb-0">Nuevo Proveedor</h5>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_proveedores.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">RUT Proveedor:</label>
                                <input type="text" class="form-control" name="rut_proveedor" placeholder="ej: 76543210-K" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Razón Social:</label>
                                <input type="text" class="form-control" name="razon_social" placeholder="ej: Agrícola del Valle S.A." required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Proveedor</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Listado de Proveedores</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>RUT</th>
                                    <th>Razón Social</th>
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
