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
        $rut = trim($_POST['rut_cliente'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: El RUT y la Razon Social son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_CLIENTE(:p_rut, :p_razon, :p_dir); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);
            oci_bind_by_name($stmt, ':p_dir', $direccion);

            if (@oci_execute($stmt)) {
                $mensaje = "Cliente '$razon' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                // $mensaje = "Error al crear cliente: " . $e['message'];
                $mensaje = "Error: " . mensaje_error($e['message']);
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $rut = trim($_POST['rut_cliente'] ?? '');
        $razon = trim($_POST['razon_social'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if ($id_cliente === false || empty($rut) || empty($razon)) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_CLIENTE(:p_id, :p_rut, :p_razon, :p_dir); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_cliente);
            oci_bind_by_name($stmt, ':p_rut', $rut);
            oci_bind_by_name($stmt, ':p_razon', $razon);
            oci_bind_by_name($stmt, ':p_dir', $direccion);

            if (@oci_execute($stmt)) {
                $mensaje = "Cliente ID $id_cliente actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                // $mensaje = "Error al actualizar cliente: " . $e['message'];
                $mensaje = "Error: " . mensaje_error($e['message']);
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_cliente = filter_input(INPUT_POST, 'id_cliente_eliminar', FILTER_VALIDATE_INT);
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_CLIENTE(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cliente);

        if (@oci_execute($stmt)) {
            $mensaje = "Cliente ID $id_cliente eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cliente porque tiene pedidos asociados.";
            } else {
                // $mensaje = "Error al eliminar: " . $e['message'];
                $mensaje = "Error: " . mensaje_error($e['message']);
            }
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_clientes.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listado
$clientes = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_CLIENTES');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($clientes as $item) {
        if ($item['ID_CLIENTE'] == $_GET['editar']) {
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
    <title>Mantenedor de Clientes</title>
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
                        <?php echo $editando ? 'Editar Cliente #' . $editando['ID_CLIENTE'] : 'Nuevo Cliente'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_clientes.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_cliente" value="<?php echo $editando['ID_CLIENTE']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">RUT:</label>
                                <input type="text" class="form-control" name="rut_cliente" placeholder="ej: 76111222-3" required
                                       value="<?php echo htmlspecialchars($editando['RUT_CLIENTE'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Razon Social:</label>
                                <input type="text" class="form-control" name="razon_social" placeholder="ej: Supermercados Lider" required
                                       value="<?php echo htmlspecialchars($editando['RAZON_SOCIAL'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Direccion:</label>
                                <input type="text" class="form-control" name="direccion" placeholder="ej: Av. Central 456"
                                       value="<?php echo htmlspecialchars($editando['DIRECCION'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Cliente' : 'Guardar Cliente'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_clientes.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Clientes</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>RUT</th>
                                    <th>Razon Social</th>
                                    <th>Direccion</th>
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
                                                <a href="mantenedor_clientes.php?editar=<?php echo $c['ID_CLIENTE']; ?>" class="btn btn-sm btn-warning">Editar</a>
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
