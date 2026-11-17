<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    // header("Location: index.php"); 
    // exit;
}
require 'db.php';

$mensaje = "";
$error_ocurrido = false;
$cuarteles = [];
$personal = [];
$contenedores = [];

// --- 1. LÓGICA DE RECEPCIÓN ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    

    $id_cuartel = filter_input(INPUT_POST, 'id_cuartel', FILTER_VALIDATE_INT);
    $id_personal = filter_input(INPUT_POST, 'id_personal', FILTER_VALIDATE_INT); 
    $kilos = filter_input(INPUT_POST, 'kilos', FILTER_VALIDATE_FLOAT);
    $id_contenedor = filter_input(INPUT_POST, 'id_contenedor', FILTER_VALIDATE_INT);

    if ($id_cuartel === false || $id_personal === false || $kilos === false || $kilos <= 0 || $id_contenedor === false) {
        $error_ocurrido = true;
        $mensaje = "Error: Datos incompletos. Asegúrese de seleccionar Cuartel, Personal, Kilos (> 0) y Contenedor.";
    } else {

        $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_GESTION_RECEPCION(:p_id_cuartel, :p_id_personal, :p_kilos, :p_id_contenedor); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_id_cuartel', $id_cuartel);
        oci_bind_by_name($stmt, ':p_id_personal', $id_personal);
        oci_bind_by_name($stmt, ':p_kilos', $kilos);
        oci_bind_by_name($stmt, ':p_id_contenedor', $id_contenedor);
        
        if (@oci_execute($stmt)) {
            $litros_estimados = $kilos * 0.70; 
            $mensaje = "Recepción registrada. Lote creado (aprox. " . number_format($litros_estimados, 2, ',', '.') . " Litros).";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            $mensaje = "Error al Registrar: " . $e['message'];
        }
        oci_free_statement($stmt);
    }
    

    header('Location: gestor_recepcion.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

// --- 2. LÓGICA DE CARGA DE DATOS  ---

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// 2.1 Cargar Cuarteles
$sql_cuarteles = "SELECT id_cuartel, nombre_cuartel, nombre_variedad FROM CA_EA_ST_IY_CUARTEL c JOIN CA_EA_ST_IY_VARIEDAD_UVA v ON c.id_variedad = v.id_variedad ORDER BY nombre_cuartel";
$stmt_cuarteles = oci_parse($conn, $sql_cuarteles);
@oci_execute($stmt_cuarteles);
while ($row = oci_fetch_assoc($stmt_cuarteles)) {
    $cuarteles[] = $row;
}
oci_free_statement($stmt_cuarteles);

// 2.2 Cargar Personal 
$sql_personal = "SELECT id_personal, nombre, apellido1, nombre_cargo FROM CA_EA_ST_IY_PERSONAL p JOIN CA_EA_ST_IY_CARGO c ON p.id_cargo = c.id_cargo ORDER BY nombre";
$stmt_personal = oci_parse($conn, $sql_personal);
@oci_execute($stmt_personal);
while ($row = oci_fetch_assoc($stmt_personal)) {
    $personal[] = $row;
}
oci_free_statement($stmt_personal);


// 2.3 Cargar Contenedores LIBRES
$cursor_cont = oci_new_cursor($conn);
$sql_cont = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_REPORTE_OCUPACION_BODEGA(:cursor); END;'; 
$stmt_cont = oci_parse($conn, $sql_cont);
oci_bind_by_name($stmt_cont, ':cursor', $cursor_cont, -1, OCI_B_CURSOR);
@oci_execute($stmt_cont);
@oci_execute($cursor_cont);

while ($row = oci_fetch_assoc($cursor_cont)) {
    if ($row['ESTADO'] == 'LIBRE') { 
        $contenedores[] = $row;
    }
}
oci_free_statement($stmt_cont);
oci_free_statement($cursor_cont);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Recepción Vendimia</title>
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
                Gestor de Recepción de Vendimia
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver al Panel</a>
        
        <h2 class="mb-4">Registro de Ingreso de Cosecha</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">Datos de la Cosecha</div>
            <div class="card-body">
                <form method="POST"> 
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cuartel" class="form-label">Cuartel (Origen):</label>
                            <select id="cuartel" name="id_cuartel" class="form-select" required>
                                <option value="">Seleccione Cuartel</option>
                                <?php foreach ($cuarteles as $cuartel): ?>
                                    <option value="<?php echo $cuartel['ID_CUARTEL']; ?>">
                                        <?php echo htmlspecialchars($cuartel['NOMBRE_CUARTEL']); ?> (<?php echo htmlspecialchars($cuartel['NOMBRE_VARIEDAD']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_personal" class="form-label">Personal a Cargo:</label>
                            <select id="id_personal" name="id_personal" class="form-select" required>
                                <option value="">Seleccione Personal</option>
                                <?php foreach ($personal as $p): ?>
                                    <option value="<?php echo $p['ID_PERSONAL']; ?>">
                                        <?php echo htmlspecialchars($p['NOMBRE'] . ' ' . $p['APELLIDO1']); ?> (<?php echo htmlspecialchars($p['NOMBRE_CARGO']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="kilos" class="form-label">Kilos de Uva Recibidos:</label>
                            <input type="number" step="0.01" min="1" id="kilos" name="kilos" class="form-control" required>
                            <div class="form-text">Se estimarán los Litros del Lote (Kilos * 0.70).</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_contenedor" class="form-label">Contenedor de Destino (Libre):</label>
                            <select id="id_contenedor" name="id_contenedor" class="form-select" required>
                                <option value="">Seleccione Contenedor Libre</option>
                                <?php if (empty($contenedores)): ?>
                                    <option disabled>--- No hay contenedores libres ---</option>
                                <?php else: ?>
                                    <?php foreach ($contenedores as $cont): ?>
                                        <option value="<?php echo $cont['ID_CONTENEDOR']; ?>">
                                            ID <?php echo $cont['ID_CONTENEDOR']; ?> - <?php echo htmlspecialchars($cont['MATERIAL']); ?> (Cap: <?php echo number_format((float)$cont['CAPACIDAD'], 0, '', '.'); ?> L)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3" <?php echo empty($contenedores) ? 'disabled' : ''; ?>>
                        Registrar Recepción y Crear Lote
                    </button>
                    <?php if (empty($contenedores)): ?>
                        <p class="text-danger mt-2">No se puede registrar sin un contenedor libre.</p>
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