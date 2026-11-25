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
        $nombre = trim($_POST['nombre_variedad'] ?? '');

        if (empty($nombre)) {
            $error_ocurrido = true;
            $mensaje = "Error: El nombre de la variedad es obligatorio.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_VARIEDAD(:p_nombre); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);

            if (@oci_execute($stmt)) {
                $mensaje = "Variedad '$nombre' registrada con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear variedad: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_variedad = filter_input(INPUT_POST, 'id_variedad', FILTER_VALIDATE_INT);
        $nombre = trim($_POST['nombre_variedad'] ?? '');

        if ($id_variedad === false || empty($nombre)) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_VARIEDAD(:p_id, :p_nombre); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_variedad);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);

            if (@oci_execute($stmt)) {
                $mensaje = "Variedad ID $id_variedad actualizada con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al actualizar variedad: " . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_variedad = filter_input(INPUT_POST, 'id_variedad_eliminar', FILTER_VALIDATE_INT);
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_VARIEDAD(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_variedad);

        if (@oci_execute($stmt)) {
            $mensaje = "Variedad ID $id_variedad eliminada correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar la variedad porque tiene cuarteles asociados.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_variedades.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listado
$variedades = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_VARIEDADES');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($variedades as $item) {
        if ($item['ID_VARIEDAD'] == $_GET['editar']) {
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
    <title>Mantenedor de Variedades de Uva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Variedades de Uva</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Variedades de Uva</h2>
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
                        <?php echo $editando ? 'Editar Variedad #' . $editando['ID_VARIEDAD'] : 'Nueva Variedad'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_variedades.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_variedad" value="<?php echo $editando['ID_VARIEDAD']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nombre de Variedad:</label>
                                <input type="text" class="form-control" name="nombre_variedad" placeholder="ej: Cabernet Sauvignon" required
                                       value="<?php echo htmlspecialchars($editando['NOMBRE_VARIEDAD'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Variedad' : 'Guardar Variedad'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_variedades.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Variedades</div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Variedad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($variedades)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No hay variedades registradas.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($variedades as $v): ?>
                                        <tr>
                                            <td><?php echo $v['ID_VARIEDAD']; ?></td>
                                            <td><?php echo htmlspecialchars($v['NOMBRE_VARIEDAD']); ?></td>
                                            <td>
                                                <a href="mantenedor_variedades.php?editar=<?php echo $v['ID_VARIEDAD']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                                <form action="mantenedor_variedades.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar variedad?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_variedad_eliminar" value="<?php echo $v['ID_VARIEDAD']; ?>">
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
