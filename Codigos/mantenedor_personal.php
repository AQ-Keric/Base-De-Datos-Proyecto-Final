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
        $rut = trim($_POST['rut'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $id_cargo = filter_input(INPUT_POST, 'id_cargo', FILTER_VALIDATE_INT);

        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_PERSONAL(:p_rut, :p_nom, :p_ape, :p_cargo); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_rut', $rut);
        oci_bind_by_name($stmt, ':p_nom', $nombre);
        oci_bind_by_name($stmt, ':p_ape', $apellido);
        oci_bind_by_name($stmt, ':p_cargo', $id_cargo);

        if (@oci_execute($stmt)) {
            $mensaje = "Personal guardado correctamente.";
        } else {
            $e = oci_error($stmt);
            $mensaje = "Error al crear personal: " . $e['message'];
            $error_ocurrido = true;
        }
        oci_free_statement($stmt);
    }

    if ($accion === 'editar') {
        $id_personal = filter_input(INPUT_POST, 'id_personal', FILTER_VALIDATE_INT);
        $rut = trim($_POST['rut'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $id_cargo = filter_input(INPUT_POST, 'id_cargo', FILTER_VALIDATE_INT);

        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_PERSONAL(:p_id, :p_rut, :p_nom, :p_ape, :p_cargo); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_personal);
        oci_bind_by_name($stmt, ':p_rut', $rut);
        oci_bind_by_name($stmt, ':p_nom', $nombre);
        oci_bind_by_name($stmt, ':p_ape', $apellido);
        oci_bind_by_name($stmt, ':p_cargo', $id_cargo);

        if (@oci_execute($stmt)) {
            $mensaje = "Personal actualizado correctamente.";
        } else {
            $e = oci_error($stmt);
            $mensaje = "Error al actualizar personal: " . $e['message'];
            $error_ocurrido = true;
        }
        oci_free_statement($stmt);
    }

    if ($accion === 'eliminar') {
        $id_personal = filter_input(INPUT_POST, 'id_personal_eliminar', FILTER_VALIDATE_INT);

        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_PERSONAL(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_personal);

        if (@oci_execute($stmt)) {
            $mensaje = "Personal ID $id_personal eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            if ($e['code'] == 2292) {
                $mensaje = "Error: El personal ID $id_personal no puede ser eliminado porque tiene registros historicos asociados.";
            } else {
                $mensaje = "Error al eliminar personal: " . $e['message'];
            }
            $error_ocurrido = true;
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_personal.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listados desde procedimientos con cursor
$cargos = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_CARGOS');
$personal_list = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PERSONAL');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($personal_list as $item) {
        if ($item['ID_PERSONAL'] == $_GET['editar']) {
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
    <title>Mantenedor de Personal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Personal</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gestion de Personal</h2>
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
                        <?php echo $editando ? 'Editar Personal #' . $editando['ID_PERSONAL'] : 'Registrar Nuevo Personal'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="mantenedor_personal.php">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_personal" value="<?php echo $editando['ID_PERSONAL']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">RUT</label>
                                <input type="text" class="form-control" name="rut" required placeholder="Ej: 12345678-9"
                                       value="<?php echo htmlspecialchars($editando['RUT'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" required placeholder="Ej: Pedro"
                                       value="<?php echo htmlspecialchars($editando['NOMBRE'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" name="apellido" required placeholder="Ej: Pascal"
                                       value="<?php echo htmlspecialchars($editando['APELLIDO1'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cargo</label>
                                <select class="form-select" name="id_cargo" required>
                                    <?php foreach ($cargos as $cargo): ?>
                                        <option value="<?php echo $cargo['ID_CARGO']; ?>"
                                            <?php echo ($editando && $editando['ID_CARGO'] == $cargo['ID_CARGO']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cargo['NOMBRE_CARGO']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Personal' : 'Guardar Personal'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_personal.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Personal</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Apellido</th>
                                    <th>RUT</th>
                                    <th>Cargo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($personal_list)): ?>
                                    <tr><td colspan="6" class="text-center text-muted">No hay personal registrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($personal_list as $row): ?>
                                    <tr>
                                        <td><?php echo $row['ID_PERSONAL']; ?></td>
                                        <td><?php echo htmlspecialchars($row['NOMBRE']); ?></td>
                                        <td><?php echo htmlspecialchars($row['APELLIDO1']); ?></td>
                                        <td><?php echo htmlspecialchars($row['RUT']); ?></td>
                                        <td><?php echo htmlspecialchars($row['NOMBRE_CARGO']); ?></td>
                                        <td>
                                            <a href="mantenedor_personal.php?editar=<?php echo $row['ID_PERSONAL']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar personal?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_personal_eliminar" value="<?php echo $row['ID_PERSONAL']; ?>">
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
