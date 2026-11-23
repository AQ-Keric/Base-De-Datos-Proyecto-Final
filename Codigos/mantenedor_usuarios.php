<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// Perfiles
$perfiles = [];
$sql_perf = "SELECT id_perfil, nombre_perfil FROM CA_EA_ST_IY_PERFIL ORDER BY id_perfil";
$stmt_perf = oci_parse($conn, $sql_perf);
if (@oci_execute($stmt_perf)) {
    while ($row = oci_fetch_assoc($stmt_perf)) {
        $perfiles[] = $row;
    }
}
oci_free_statement($stmt_perf);

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';

    // Crear
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $id_perfil = filter_input(INPUT_POST, 'id_perfil', FILTER_VALIDATE_INT);

        if (empty($nombre) || empty($email) || empty($password) || $id_perfil === false) {
            $error_ocurrido = true;
            $mensaje = "Error: Todos los campos son obligatorios.";
        } else {
            $sql = 'INSERT INTO CA_EA_ST_IY_USUARIOS (id_perfil, nombre, password, email) VALUES (:p_perf, :p_nom, :p_pass, :p_mail)';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_perf', $id_perfil);
            oci_bind_by_name($stmt, ':p_nom', $nombre);
            oci_bind_by_name($stmt, ':p_pass', $password);
            oci_bind_by_name($stmt, ':p_mail', $email);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "Usuario '$nombre' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                if (strpos($e['message'], 'unique constraint') !== false || strpos($e['message'], 'ORA-00001') !== false) {
                    $mensaje = "Error: El correo electronico ya esta registrado.";
                } else {
                    $mensaje = "Error al crear usuario: " . $e['message'];
                }
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }

    // Eliminar
    if ($accion === 'eliminar') {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario_eliminar', FILTER_VALIDATE_INT);

        if ($id_usuario == $_SESSION['id_usuario']) {
            $error_ocurrido = true;
            $mensaje = "Error: No puedes eliminar tu propio usuario en sesion activa.";
        } else {
            $sql = 'DELETE FROM CA_EA_ST_IY_USUARIOS WHERE id_usuario = :p_id';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_usuario);

            if (@oci_execute($stmt)) {
                oci_commit($conn);
                $mensaje = "Usuario ID $id_usuario eliminado correctamente.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error al eliminar: " . $e['message'];
                oci_rollback($conn);
            }
            oci_free_statement($stmt);
        }
    }

    header('Location: mantenedor_usuarios.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Listado
$usuarios = [];
$sql_list = "SELECT u.id_usuario, u.nombre, u.email, p.nombre_perfil
             FROM CA_EA_ST_IY_USUARIOS u
             JOIN CA_EA_ST_IY_PERFIL p ON u.id_perfil = p.id_perfil
             ORDER BY u.id_usuario";
$stmt_list = oci_parse($conn, $sql_list);
if (@oci_execute($stmt_list)) {
    while ($row = oci_fetch_assoc($stmt_list)) {
        $usuarios[] = $row;
    }
}
oci_free_statement($stmt_list);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Vina</a>
            <span class="navbar-text text-white">Mantenedor de Usuarios</span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mantenedor de Usuarios</h2>
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
                        <h5 class="card-title mb-0">Nuevo Usuario</h5>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_usuarios.php" method="POST">
                            <input type="hidden" name="accion" value="crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre Completo:</label>
                                <input type="text" class="form-control" name="nombre" placeholder="ej: Juan Pérez" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico:</label>
                                <input type="email" class="form-control" name="email" placeholder="ej: juan@vina.cl" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña:</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Perfil / Rol:</label>
                                <select class="form-select" name="id_perfil" required>
                                    <option value="">Seleccione Perfil...</option>
                                    <?php foreach ($perfiles as $p): ?>
                                        <option value="<?php echo $p['ID_PERFIL']; ?>">
                                            <?php echo htmlspecialchars($p['NOMBRE_PERFIL']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Usuario</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">Listado de Usuarios</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Perfil</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No hay usuarios registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr>
                                            <td><?php echo $u['ID_USUARIO']; ?></td>
                                            <td><?php echo htmlspecialchars($u['NOMBRE']); ?></td>
                                            <td><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['NOMBRE_PERFIL']); ?></span></td>
                                            <td>
                                                <form action="mantenedor_usuarios.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar usuario?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_usuario_eliminar" value="<?php echo $u['ID_USUARIO']; ?>">
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
