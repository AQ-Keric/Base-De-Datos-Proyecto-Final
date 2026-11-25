<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;

// Cargos
$cargos = [];
$sql_cargos = "SELECT ID_CARGO, NOMBRE_CARGO FROM CA_EA_ST_IY_CARGO ORDER BY ID_CARGO";
$stmt_cargos = oci_parse($conn, $sql_cargos);

if (@oci_execute($stmt_cargos)) { 
    while ($row = oci_fetch_assoc($stmt_cargos)) {
        $cargos[] = $row;
    }
    @oci_free_statement($stmt_cargos);
} 

// Guardar cambios
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = $_POST['accion'] ?? '';
    
    // Crear
    if ($accion === 'crear') {
        $rut = $_POST['rut'];
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido']; 
        $id_cargo = filter_input(INPUT_POST, 'id_cargo', FILTER_VALIDATE_INT);
        
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_CREAR_PERSONAL(:p_rut, :p_nom, :p_ape, :p_cargo); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_rut', $rut);
        oci_bind_by_name($stmt, ':p_nom', $nombre);
        oci_bind_by_name($stmt, ':p_ape', $apellido);
        oci_bind_by_name($stmt, ':p_cargo', $id_cargo); 
        
        if (@oci_execute($stmt)) {
            oci_commit($conn);
            $mensaje = "Personal guardado correctamente.";
        } else {
            $e = oci_error($stmt);
            $mensaje = "Error al crear personal: " . $e['message'];
            $error_ocurrido = true;
        }
        oci_free_statement($stmt);
    }
    
    // Eliminar
    if ($accion === 'eliminar') {
        $id_personal = filter_input(INPUT_POST, 'id_personal_eliminar', FILTER_VALIDATE_INT);
        
        $sql = 'BEGIN CA_EA_ST_IY_SP_CRUD_DEL_PERSONAL(:p_id); END;';
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':p_id', $id_personal);
        
        if (@oci_execute($stmt)) {
            oci_commit($conn);
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
}

// Listado

$sql_list = "SELECT 
                P.ID_PERSONAL, 
                P.RUT, 
                P.NOMBRE, 
                C.NOMBRE_CARGO
             FROM CA_EA_ST_IY_PERSONAL P
             LEFT JOIN CA_EA_ST_IY_CARGO C ON P.ID_CARGO = C.ID_CARGO 
             ORDER BY P.ID_PERSONAL";
             
$stmt_list = oci_parse($conn, $sql_list);
$list_execution_exitosa = oci_execute($stmt_list); 
             
if (!$list_execution_exitosa) {
    $e = oci_error($stmt_list);

    $mensaje = "Error al cargar la lista de personal: " . $e['message'];
    $error_ocurrido = true;
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenedor de Personal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header-custom { background-color: #722F37; color: white; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gestión de Personal</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header card-header-custom">Registrar Nuevo Empleado</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="crear">
                            
                            <div class="mb-3">
                                <label for="rut" class="form-label">RUT</label>
                                <input type="text" class="form-control" id="rut" name="rut" required placeholder="Ej: 12345678-9">
                            </div>
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Pedro">
                            </div>
                            <div class="mb-3">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" required placeholder="Ej: Pascal">
                            </div>
                            <div class="mb-3">
                                <label for="id_cargo" class="form-label">Cargo</label>
                                <select class="form-select" id="id_cargo" name="id_cargo" required>
                                    <?php if (empty($cargos)): ?>
                                        <option value="">(⚠️ No se cargaron Cargos)</option>
                                    <?php else: ?>
                                        <?php foreach ($cargos as $cargo): ?>
                                            <option value="<?php echo $cargo['ID_CARGO']; ?>"><?php echo $cargo['NOMBRE_CARGO']; ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Personal</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header card-header-custom">Lista de Personal Registrado</div>
                    <div class="card-body">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>RUT</th>
                                    <th>Cargo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($list_execution_exitosa): 
                                ?>
                                    <?php while ($row = oci_fetch_assoc($stmt_list)): ?>
                                    <tr>
                                        <td><?php echo $row['ID_PERSONAL']; ?></td>
                                        <td><?php echo $row['NOMBRE']; ?></td> 
                                        <td><?php echo $row['RUT']; ?></td>
                                        <td><?php echo $row['NOMBRE_CARGO']; ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar a <?php echo htmlspecialchars($row['NOMBRE']); ?>?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_personal_eliminar" value="<?php echo $row['ID_PERSONAL']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-danger">
                                            No se pudo cargar la lista. Por favor, revise el error de Oracle.
                                        </td>
                                    </tr>
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
<?php
if (isset($stmt_list) && $stmt_list) {
    oci_free_statement($stmt_list);
}
oci_close($conn);
?>