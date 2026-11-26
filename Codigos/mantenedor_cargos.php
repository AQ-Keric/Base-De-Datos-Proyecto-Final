<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['perfil'] != 1) {
    header("Location: dashboard.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_cargo'] ?? '');

        if (empty($nombre)) {
            $error_ocurrido = true;
            $mensaje = "Error: El nombre del cargo es obligatorio.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_CARGO(:p_nombre); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);

            if (@oci_execute($stmt)) {
                $mensaje = "Cargo '$nombre' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear cargo: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_cargo = filter_input(INPUT_POST, 'id_cargo', FILTER_VALIDATE_INT);
        $nombre = trim($_POST['nombre_cargo'] ?? '');

        if ($id_cargo === false || empty($nombre)) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_CARGO(:p_id, :p_nombre); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_cargo);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);

            if (@oci_execute($stmt)) {
                $mensaje = "Cargo ID $id_cargo actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar cargo: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_cargo = filter_input(INPUT_POST, 'id_cargo_eliminar', FILTER_VALIDATE_INT);
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_CARGO(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cargo);

        if (@oci_execute($stmt)) {
            $mensaje = "Cargo ID $id_cargo eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cargo porque tiene personal asociado.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_cargos.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listado
$cargos = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_CARGOS');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($cargos as $item) {
        if ($item['ID_CARGO'] == $_GET['editar']) {
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
    <title>Mantenedor de Cargos</title>
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
            <span class="navbar-text text-white">Mantenedor de Cargos</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Cargos</h2>
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
                        <?php echo $editando ? 'Editar Cargo #' . $editando['ID_CARGO'] : 'Nuevo Cargo'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_cargos.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_cargo" value="<?php echo $editando['ID_CARGO']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nombre del Cargo:</label>
                                <input type="text" class="form-control" name="nombre_cargo" placeholder="ej: Enologo Jefe, Bodeguero" required
                                       value="<?php echo htmlspecialchars($editando['NOMBRE_CARGO'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Cargo' : 'Guardar Cargo'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_cargos.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Cargos</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Cargo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cargos)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No hay cargos registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cargos as $c): ?>
                                        <tr>
                                            <td><?php echo $c['ID_CARGO']; ?></td>
                                            <td><?php echo htmlspecialchars($c['NOMBRE_CARGO']); ?></td>
                                            <td>
                                                <a href="mantenedor_cargos.php?editar=<?php echo $c['ID_CARGO']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                                <form action="mantenedor_cargos.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar cargo?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_cargo_eliminar" value="<?php echo $c['ID_CARGO']; ?>">
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
