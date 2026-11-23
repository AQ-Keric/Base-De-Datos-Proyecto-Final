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
        $rut = trim($_POST['rut_cliente'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Chile');

        if (empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: El RUT y la Razón Social son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_CREAR_CLIENTE(:p_rut, :p_razon, :p_dir); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);
            oci_bind_by_name($stmt, ':p_dir', $direccion);

            if (@oci_execute($stmt)) {
                $mensaje = "Cliente '$razon' registrado con éxito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear cliente: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    // Actualizar
    if ($accion === 'actualizar') {
        $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $direccion = trim($_POST['direccion'] ?? '');

        if ($id_cliente === false || empty($direccion)) {
            $error_ocurrido = true;
            $mensaje = "Error: ID de cliente y dirección requeridos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_CRUD_UPDATE_CLIENTE(:p_id, :p_dir); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_cliente);
            oci_bind_by_name($stmt, ':p_dir', $direccion);

            if (@oci_execute($stmt)) {
                $mensaje = "Dirección del cliente ID $id_cliente actualizada.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar cliente: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id_cliente = filter_input(INPUT_POST, 'id_cliente_eliminar', FILTER_VALIDATE_INT);
        $sql = 'DELETE FROM CA_EA_ST_IY_CLIENTE WHERE id_cliente = :p_id';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cliente);

        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Cliente ID $id_cliente eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cliente porque tiene pedidos asociados.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_clientes.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Listado
$clientes = [];
$sql_list = "SELECT id_cliente, rut_cliente, razon_social, direccion, pais FROM CA_EA_ST_IY_CLIENTE ORDER BY id_cliente DESC";
$stmt_list = oci_parse($conn, $sql_list);
if (@oci_execute($stmt_list)) {
    while ($row = oci_fetch_assoc($stmt_list)) {
        $clientes[] = $row;
    }
}
oci_free_statement($stmt_list);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Clientes</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Clientes</h2>
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
                        <h5 class="card-title mb-0">Nuevo Cliente</h5>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_clientes.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">RUT:</label>
                                <input type="text" class="form-control" name="rut_cliente" placeholder="ej: 76111222-3" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Razón Social:</label>
                                <input type="text" class="form-control" name="razon_social" placeholder="ej: Supermercados Lider" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección:</label>
                                <input type="text" class="form-control" name="direccion" placeholder="ej: Av. Central 456">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Cliente</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Listado de Clientes</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>RUT</th>
                                    <th>Razón Social</th>
                                    <th>Dirección</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clientes)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No hay clientes registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($clientes as $c): ?>
                                        <tr>
                                            <td><?php echo $c['ID_CLIENTE']; ?></td>
                                            <td><?php echo htmlspecialchars($c['RUT_CLIENTE']); ?></td>
                                            <td><?php echo htmlspecialchars($c['RAZON_SOCIAL']); ?></td>
                                            <td><?php echo htmlspecialchars($c['DIRECCION'] ?? '-'); ?></td>
                                            <td>
                                                <form action="mantenedor_clientes.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar cliente?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_cliente_eliminar" value="<?php echo $c['ID_CLIENTE']; ?>">
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
