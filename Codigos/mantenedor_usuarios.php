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
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $id_perfil = filter_input(INPUT_POST, 'id_perfil', FILTER_VALIDATE_INT);

        if (empty($nombre) || empty($email) || empty($password) || $id_perfil === false) {
            $error_ocurrido = true;
            $mensaje = "Error: Todos los campos son obligatorios.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_USUARIO(:p_perf, :p_nom, :p_pass, :p_mail); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_perf', $id_perfil);
            oci_bind_by_name($stmt, ':p_nom', $nombre);
            oci_bind_by_name($stmt, ':p_pass', $password);
            oci_bind_by_name($stmt, ':p_mail', $email);

            if (@oci_execute($stmt)) {
                $mensaje = "Usuario '$nombre' registrado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                if (strpos($e['message'], 'unique constraint') !== false || strpos($e['message'], 'ORA-00001') !== false) {
                    $mensaje = "Error: El correo electronico ya esta registrado.";
                } else {
                    $mensaje = "Error: " . mensaje_error($e['message']);
                }
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'editar') {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $id_perfil = filter_input(INPUT_POST, 'id_perfil', FILTER_VALIDATE_INT);

        if ($id_usuario === false || empty($nombre) || empty($email) || $id_perfil === false) {
            $error_ocurrido = true;
            $mensaje = "Error: Datos incompletos.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_UPDATE_USUARIO(:p_id, :p_perf, :p_nom, :p_mail); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_usuario);
            oci_bind_by_name($stmt, ':p_perf', $id_perfil);
            oci_bind_by_name($stmt, ':p_nom', $nombre);
            oci_bind_by_name($stmt, ':p_mail', $email);

            if (@oci_execute($stmt)) {
                $mensaje = "Usuario ID $id_usuario actualizado con exito.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error: " . mensaje_error($e['message']);
            }
            oci_free_statement($stmt);
        }
    }

    if ($accion === 'eliminar') {
        $id_usuario = filter_input(INPUT_POST, 'id_usuario_eliminar', FILTER_VALIDATE_INT);

        if ($id_usuario == $_SESSION['id_usuario']) {
            $error_ocurrido = true;
            $mensaje = "Error: No puedes eliminar tu propio usuario.";
        } else {
            $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_USUARIO(:p_id); END;';
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':p_id', $id_usuario);

            if (@oci_execute($stmt)) {
                $mensaje = "Usuario ID $id_usuario eliminado correctamente.";
            } else {
                $e = oci_error($stmt);
                $error_ocurrido = true;
                $mensaje = "Error: " . mensaje_error($e['message']);
            }
            oci_free_statement($stmt);
        }
    }

    header('Location: mantenedor_usuarios.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = ($_GET['error'] ?? '0') == '1';
}

// Listados
$perfiles = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PERFILES');
$usuarios = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_USUARIOS');

$editando = null;
if (isset($_GET['editar'])) {
    foreach ($usuarios as $item) {
        if ($item['ID_USUARIO'] == $_GET['editar']) {
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
    <title>Mantenedor de Usuarios</title>
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
                        <?php echo $editando ? 'Editar Usuario #' . $editando['ID_USUARIO'] : 'Nuevo Usuario'; ?>
                    </div>
                    <div class="card-body">
                        <form action="mantenedor_usuarios.php" method="POST">
                            <input type="hidden" name="accion" value="<?php echo $editando ? 'editar' : 'crear'; ?>">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id_usuario" value="<?php echo $editando['ID_USUARIO']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nombre Completo:</label>
                                <input type="text" class="form-control" name="nombre" placeholder="ej: Juan Perez" required
                                       value="<?php echo htmlspecialchars($editando['NOMBRE'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correo Electronico:</label>
                                <input type="email" class="form-control" name="email" placeholder="ej: juan@vina.cl" required
                                       value="<?php echo htmlspecialchars($editando['EMAIL'] ?? ''); ?>">
                            </div>
                            <?php if (!$editando): ?>
                            <div class="mb-3">
                                <label class="form-label">Contrasena:</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Perfil / Rol:</label>
                                <select class="form-select" name="id_perfil" required>
                                    <option value="">Seleccione Perfil...</option>
                                    <?php foreach ($perfiles as $p): ?>
                                        <option value="<?php echo $p['ID_PERFIL']; ?>"
                                            <?php echo ($editando && $editando['ID_PERFIL'] == $p['ID_PERFIL']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['NOMBRE_PERFIL']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editando ? 'Actualizar Usuario' : 'Guardar Usuario'; ?>
                            </button>
                            <?php if ($editando): ?>
                                <a href="mantenedor_usuarios.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">Listado de Usuarios</div>
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
                                            <td><?php echo htmlspecialchars($u['NOMBRE_PERFIL']); ?></td>
                                            <td>
                                                <a href="mantenedor_usuarios.php?editar=<?php echo $u['ID_USUARIO']; ?>" class="btn btn-sm btn-warning">Editar</a>
                                                <?php if ($u['ID_USUARIO'] != $_SESSION['id_usuario']): ?>
                                                <form action="mantenedor_usuarios.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar usuario?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_usuario_eliminar" value="<?php echo $u['ID_USUARIO']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                                </form>
                                                <?php endif; ?>
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
