<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
require 'db.php'; 

$mensaje = "";
$error_ocurrido = false;
$lotes = []; 
$contenedores = [];

// Trasvasijado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_lote = filter_input(INPUT_POST, 'id_lote', FILTER_VALIDATE_INT);
    $nuevo_contenedor = filter_input(INPUT_POST, 'nuevo_contenedor', FILTER_VALIDATE_INT);

    if ($id_lote === false || $nuevo_contenedor === false) {
        $error_ocurrido = true;
        $mensaje = "Error: Lote y Nuevo Contenedor son obligatorios.";
    } else {
        $sql = 'BEGIN CA_EA_ST_IY_SP_GESTION_TRASVASIJADO(:p_lote, :p_cont); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_lote', $id_lote);
        oci_bind_by_name($stmt, ':p_cont', $nuevo_contenedor);
        
        if (@oci_execute($stmt)) {
            $mensaje = "Trasvasijado del Lote $id_lote al Contenedor $nuevo_contenedor realizado con exito.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            // $mensaje = "Error al trasvasijar: " . $e['message'];
            $mensaje = "Error: " . mensaje_error($e['message']);
        }
        oci_free_statement($stmt);
    }

    header('Location: gestor_trasvasijado.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Cargar datos
$lotes = consultar_cursor($conn, 'CA_EA_ST_IY_SP_LISTAR_LOTES_ACTIVOS');
foreach (consultar_cursor($conn, 'CA_EA_ST_IY_SP_REPORTE_OCUPACION_BODEGA') as $row) {
    if ($row['ESTADO'] == 'LIBRE') {
        $contenedores[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de Trasvasijado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
        .table-lotes td { vertical-align: middle; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">
                Gestor de Trasvasijado de Lotes
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver al Panel</a>
        
        <h2 class="mb-4">Proceso de Trasvasijado (Movimiento de Lote)</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">Lotes Disponibles para Mover</div>
            <div class="card-body">
                <p class="text-muted">Seleccione un lote para moverlo a un nuevo contenedor.</p>
                
                <?php if (empty($lotes)): ?>
                    <div class="alert alert-info">No hay lotes de vino activos para trasvasijar.</div>
                <?php elseif (empty($contenedores)): ?>
                    <div class="alert alert-warning">No hay contenedores libres para recibir el trasvasijado.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm table-lotes">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID Lote</th>
                                    <th>Variedad</th>
                                    <th>Litros</th>
                                    <th>Estado</th>
                                    <th>Contenedor Actual</th>
                                    <th style="width: 30%;">Trasvasijar a:</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lotes as $lote): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($lote['ID_LOTE']); ?></td>
                                    <td><?php echo htmlspecialchars($lote['NOMBRE_VARIEDAD']); ?></td>
                                    <td><?php echo number_format((float)$lote['LITROS_ACTUALES'], 2, ',', '.'); ?> L</td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($lote['ESTADO']); ?></span></td>
                                    <td><?php echo htmlspecialchars($lote['CONTENEDOR_ACTUAL']); ?></td>
                                    <td>
                                        <form method="POST" class="d-flex">
                                            <input type="hidden" name="id_lote" value="<?php echo $lote['ID_LOTE']; ?>">
                                            <select name="nuevo_contenedor" class="form-select form-select-sm me-2" required>
                                                <option value="">Seleccione Contenedor Libre</option>
                                                <?php foreach ($contenedores as $cont): ?>
                                                    <?php 
                                                        $is_current = (int)$lote['ID_CONTENEDOR'] == (int)$cont['ID_CONTENEDOR'];
                                                    ?>
                                                    <option value="<?php echo $cont['ID_CONTENEDOR']; ?>"
                                                            <?php echo $is_current ? 'disabled' : ''; ?>
                                                            title="<?php echo $is_current ? 'Contenedor actual' : ''; ?>">
                                                        ID <?php echo $cont['ID_CONTENEDOR']; ?> - <?php echo htmlspecialchars($cont['MATERIAL']); ?> (<?php echo number_format($cont['CAPACIDAD'], 0, '', '.'); ?> L)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-success flex-shrink-0" onclick="return confirm('¿Confirma el trasvasijado del Lote <?php echo $lote['ID_LOTE']; ?>?');" <?php echo empty($contenedores) ? 'disabled' : ''; ?>>
                                                Mover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php

oci_close($conn);
?>