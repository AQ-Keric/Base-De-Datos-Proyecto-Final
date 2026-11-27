<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_cuartel'] ?? '');
        $id_variedad = filter_input(INPUT_POST, 'id_variedad', FILTER_VALIDATE_INT);
        $hectareas = filter_input(INPUT_POST, 'hectareas', FILTER_VALIDATE_FLOAT);

        if (empty($nombre) || $id_variedad === false) {
            $error_ocurrido = true;
            $mensaje = "Error: El nombre y la variedad son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_CUARTEL(:p_nombre, :p_var, :p_hec); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);
            oci_bind_by_name($stmt, ':p_var', $id_variedad);
            oci_bind_by_name($stmt, ':p_hec', $hectareas);

            if (@oci_execute($stmt)) {
                $mensaje = "Cuartel '$nombre' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear cuartel: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_cuartel = filter_input(INPUT_POST, 'id_cuartel', FILTER_VALIDATE_INT);
        $nombre = trim($_POST['nombre_cuartel'] ?? '');
        $id_variedad = filter_input(INPUT_POST, 'id_variedad', FILTER_VALIDATE_INT);
        $hectareas = filter_input(INPUT_POST, 'hectareas', FILTER_VALIDATE_FLOAT);

        if ($id_cuartel === false || empty($nombre) || $id_variedad === false) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_CUARTEL(:p_id, :p_nombre, :p_var, :p_hec); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_cuartel);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);
            oci_bind_by_name($stmt, ':p_var', $id_variedad);
            oci_bind_by_name($stmt, ':p_hec', $hectareas);

            if (@oci_execute($stmt)) {
                $mensaje = "Cuartel ID $id_cuartel actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar cuartel: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_cuartel = filter_input(INPUT_POST, 'id_cuartel_eliminar', FILTER_VALIDATE_INT);
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_CUARTEL(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cuartel);

        if (@oci_execute($stmt)) {
            $mensaje = "Cuartel ID $id_cuartel eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cuartel porque tiene recepciones asociadas.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_cuarteles.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listados
$variedades = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_VARIEDADES');
$cuarteles = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_CUARTELES');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($cuarteles as $item) {
        if ($item['ID_CUARTEL'] == $_GET['editar']) {
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
    <title>Mantenedor de Cuarteles</title>
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
            <span class="navbar-text text-white">Mantenedor de Cuarteles</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Cuarteles</h2>
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
                        <?php echo $editando ? 'Editar Cuartel #' . $editando['ID_CUARTEL'] : 'Nuevo Cuartel'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_cuarteles.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_cuartel" value="<?php echo $editando['ID_CUARTEL']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nombre Cuartel:</label>
                                <input type="text" class="form-control" name="nombre_cuartel" placeholder="ej: Cuartel Los Robles" required
                                       value="<?php echo htmlspecialchars($editando['NOMBRE_CUARTEL'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Variedad de Uva:</label>
                                <select class="form-select" name="id_variedad" required>
                                    <option value="">Seleccione Variedad...</option>
                                    <?php foreach ($variedades as $v): ?>
                                        <option value="<?php echo $v['ID_VARIEDAD']; ?>"
                                            <?php echo ($editando && $editando['ID_VARIEDAD'] == $v['ID_VARIEDAD']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($v['NOMBRE_VARIEDAD']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hectareas:</label>
                                <input type="number" step="0.01" class="form-control" name="hectareas" placeholder="ej: 3.5"
                                       value="<?php echo htmlspecialchars($editando['HECTAREAS'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Cuartel' : 'Guardar Cuartel'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_cuarteles.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Cuarteles</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Cuartel</th>
                                    <th>Variedad</th>
                                    <th>Hectareas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cuarteles)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No hay cuarteles registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cuarteles as $c): ?>
                                        <tr>
                                            <td><?php echo $c['ID_CUARTEL']; ?></td>
                                            <td><?php echo htmlspecialchars($c['NOMBRE_CUARTEL']); ?></td>
                                            <td><?php echo htmlspecialchars($c['NOMBRE_VARIEDAD']); ?></td>
                                            <td><?php echo $c['HECTAREAS'] ? htmlspecialchars($c['HECTAREAS']) . ' ha' : '-'; ?></td>
                                            <td>
                                                <a href="mantenedor_cuarteles.php?editar=<?php echo $c['ID_CUARTEL']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                                <form action="mantenedor_cuarteles.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar cuartel?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_cuartel_eliminar" value="<?php echo $c['ID_CUARTEL']; ?>">
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
