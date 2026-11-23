<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// --- 1. LÓGICA DE CREATE Y DELETE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // CREAR CARGO
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_cargo'] ?? '');

        if (empty($nombre)) {
            $error_ocurrido = true;
            $mensaje = "Error: El nombre del cargo es obligatorio.";
        } else {
            $sql = 'INSERT INTO CA_EA_ST_IY_CARGO (nombre_cargo) VALUES (:p_nombre)';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_nombre', $nombre);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "Cargo '$nombre' registrado con éxito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al crear cargo: " . $e['message'];
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }

    // ELIMINAR CARGO
    if ($accion === 'eliminar') {
        $id_cargo = filter_input(INPUT_POST, 'id_cargo_eliminar', FILTER_VALIDATE_INT);
        $sql = 'DELETE FROM CA_EA_ST_IY_CARGO WHERE id_cargo = :p_id';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_cargo);

        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Cargo ID $id_cargo eliminado correctamente.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            if (strpos($e['message'], 'integrity constraint') !== false) {
                $mensaje = "Error: No se puede eliminar el cargo porque tiene personal asociado.";
            } else {
                $mensaje = "Error al eliminar: " . $e['message'];
            }
            oci_rollback($conn);
        }
        oci_free_statement($stmt);
    }

    header('Location: mantenedor_cargos.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// --- 2. LISTAR CARGOS ---
$cargos = [];
$sql_list = "SELECT id_cargo, nombre_cargo FROM CA_EA_ST_IY_CARGO ORDER BY id_cargo";
$stmt_list = oci_parse($conn, $sql_list);
if (@oci_execute($stmt_list)) {
    while ($row = oci_fetch_assoc($stmt_list)) {
        $cargos[] = $row;
    }
}
oci_free_statement($stmt_list);
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">Mantenedor de Cargos</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>👔 Mantenedor de Cargos</h2>
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
                        <h5 class="card-title mb-0">Nuevo Cargo</h5>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_cargos.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Cargo:</label>
                                <input type="text" class="form-control" name="nombre_cargo" placeholder="ej: Enólogo Jefe, Bodeguero" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Cargo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Listado de Cargos</h5>
                    </div>
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
</body>
</html>
