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
$insumos = [];
$personal = [];

// Registrar aplicacion de insumo
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_lote = filter_input(INPUT_POST, 'id_lote', FILTER_VALIDATE_INT);
    $id_insumo = filter_input(INPUT_POST, 'id_insumo', FILTER_VALIDATE_INT);
    $id_personal = filter_input(INPUT_POST, 'id_personal', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad_usada', FILTER_VALIDATE_FLOAT);
    $motivo = $_POST['motivo_uso'] ?? '';

    if ($id_lote === false || $id_insumo === false || $id_personal === false || $cantidad === false || $cantidad <= 0 || empty($motivo)) {
        $error_ocurrido = true;
        $mensaje = "Error: Todos los campos son obligatorios y la cantidad debe ser mayor a 0.";
    } else {
        $sql = 'BEGIN CA_EA_ST_IY_SP_GESTION_APLICAR_INSUMO(:p_lote, :p_insumo, :p_personal, :p_cantidad, :p_motivo); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_lote', $id_lote);
        oci_bind_by_name($stmt, ':p_insumo', $id_insumo);
        oci_bind_by_name($stmt, ':p_personal', $id_personal);
        oci_bind_by_name($stmt, ':p_cantidad', $cantidad);
        oci_bind_by_name($stmt, ':p_motivo', $motivo);
        
        if (@oci_execute($stmt)) {
            $mensaje = "Aplicacion de insumo registrada con exito. Stock actualizado.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            $mensaje = "Error: " . mensaje_error($e['message']);

            if (strpos($e['message'], 'check constraint') !== false) {
                 $mensaje = "Error: La cantidad utilizada excede el stock actual del insumo.";
            }
        }
        oci_free_statement($stmt);
    }
    
    header('Location: gestor_aplicacion_insumo.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Cargar datos
$lotes = consultar_cursor($conn, 'CA_EA_ST_IY_SP_LISTAR_LOTES_ACTIVOS');
$insumos = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_INSUMOS');
$personal = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PERSONAL');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Aplicación de Insumos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">
                Gestor de Aplicación de Insumos
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver al Panel</a>
        
        <h2 class="mb-4">Registro de Aplicación de Insumos a Lotes</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-info">
            <div class="card-header bg-info text-dark">Detalle de la Aplicación</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_lote" class="form-label">Lote Destino:</label>
                            <select id="id_lote" name="id_lote" class="form-select" required>
                                <option value="">Seleccione un Lote</option>
                                <?php foreach ($lotes as $lote): ?>
                                    <option value="<?php echo $lote['ID_LOTE']; ?>">
                                        Lote #<?php echo htmlspecialchars($lote['ID_LOTE']); ?> | <?php echo htmlspecialchars($lote['NOMBRE_VARIEDAD']); ?> (<?php echo number_format((float)$lote['LITROS_ACTUALES'], 0, '', '.'); ?> L)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_insumo" class="form-label">Insumo a Aplicar:</label>
                            <select id="id_insumo" name="id_insumo" class="form-select" required>
                                <option value="">Seleccione Insumo</option>
                                <?php foreach ($insumos as $insumo): ?>
                                    <option value="<?php echo $insumo['ID_INSUMO']; ?>">
                                        <?php echo htmlspecialchars($insumo['NOMBRE_INSUMO']); ?> (Stock: <?php echo number_format((float)$insumo['STOCK_ACTUAL'], 0, '', '.'); ?> <?php echo htmlspecialchars($insumo['UNIDAD_MEDIDA']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label for="id_personal" class="form-label">Personal Responsable:</label>
                            <select id="id_personal" name="id_personal" class="form-select" required>
                                <option value="">Seleccione Personal</option>
                                <?php foreach ($personal as $p): ?>
                                    <option value="<?php echo $p['ID_PERSONAL']; ?>">
                                        <?php echo htmlspecialchars($p['NOMBRE'] . ' ' . $p['APELLIDO1']); ?> (<?php echo htmlspecialchars($p['NOMBRE_CARGO']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cantidad_usada" class="form-label">Cantidad a Usar:</label>
                            <input type="number" step="0.001" min="0.001" id="cantidad_usada" name="cantidad_usada" class="form-control" required>
                            <div class="form-text">Usar decimales si es necesario (ej: 0.5 kg).</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motivo_uso" class="form-label">Motivo de la Aplicación:</label>
                        <textarea id="motivo_uso" name="motivo_uso" class="form-control" rows="2" required placeholder="Ej: Adición de levaduras para inicio de fermentación, sulfitado de seguridad."></textarea>
                    </div>

                    <button type="submit" class="btn btn-info w-100 mt-3" <?php echo empty($lotes) || empty($insumos) ? 'disabled' : ''; ?>>
                        Aplicar Insumo y Registrar
                    </button>
                    <?php if (empty($lotes) || empty($insumos)): ?>
                        <p class="text-danger mt-2">No se puede aplicar insumos si no hay Lotes activos o Insumos registrados.</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php

oci_close($conn);
?>