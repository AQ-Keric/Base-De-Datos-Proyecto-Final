<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// Variedades
$variedades = [];
$sql_var = "SELECT id_variedad, nombre_variedad FROM CA_EA_ST_IY_VARIEDAD_UVA ORDER BY nombre_variedad";
$stmt_var = oci_parse($conn, $sql_var);
if (@oci_execute($stmt_var)) {
    while ($row = oci_fetch_assoc($stmt_var)) {
        $variedades[] = $row;
    }
}
oci_free_statement($stmt_var);

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // Crear
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_cuartel'] ?? '');
        $id_variedad = filter_input(INPUT_POST, 'id_variedad', FILTER_VALIDATE_INT);
        $hectareas = filter_input(INPUT_POST, 'hectareas', FILTER_VALIDATE_FLOAT);

        if (empty($nombre) || $id_variedad === false) {
            $error_ocurrido = true;
            $mensaje = "Error: El nombre y la variedad son obligatorios.";
        } else {
            $sql = 'INSERT INTO CA_EA_ST_IY_CUARTEL (nombre_cuartel, id_variedad, hectareas) VALUES (:p_nombre, :p_var, :p_hec)';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);
            oci_bind_by_name($stmt, ':p_var', $id_variedad);
            oci_bind_by_name($stmt, ':p_hec', $hectareas);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "Cuartel '$nombre' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear cuartel: " . $e['message'];
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id_cuartel = filter_input(INPUT_POST, 'id_cuartel_eliminar', FILTER_VALIDATE_INT);
        $sql = 'DELETE FROM CA_EA_ST_IY_CUARTEL WHERE id_cuartel = :p_id';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cuartel);

        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Cuartel ID $id_cuartel eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cuartel porque tiene recepciones de cosecha asociadas.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_cuarteles.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Listado
$cuarteles = [];
$sql_list = "SELECT c.id_cuartel, c.nombre_cuartel, c.hectareas, v.nombre_variedad
             FROM CA_EA_ST_IY_CUARTEL c
             JOIN CA_EA_ST_IY_VARIEDAD_UVA v ON c.id_variedad = v.id_variedad
             ORDER BY c.id_cuartel";
$stmt_list = oci_parse($conn, $sql_list);
if (@oci_execute($stmt_list)) {
    while ($row = oci_fetch_assoc($stmt_list)) {
        $cuarteles[] = $row;
    }
}
oci_free_statement($stmt_list);
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
                        <h5 class="card-title mb-0">Nuevo Cuartel</h5>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_cuarteles.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre Cuartel:</label>
                                <input type="text" class="form-control" name="nombre_cuartel" placeholder="ej: Cuartel Los Robles" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Variedad de Uva:</label>
                                <select class="form-select" name="id_variedad" required>
                                    <option value="">Seleccione Variedad...</option>
                                    <?php foreach ($variedades as $v): ?>
                                        <option value="<?php echo $v['ID_VARIEDAD']; ?>">
                                            <?php echo htmlspecialchars($v['NOMBRE_VARIEDAD']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hectáreas:</label>
                                <input type="number" step="0.01" class="form-control" name="hectareas" placeholder="ej: 3.5">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Cuartel</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Listado de Cuarteles</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Cuartel</th>
                                    <th>Variedad</th>
                                    <th>Hectáreas</th>
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
</body>
</html>
