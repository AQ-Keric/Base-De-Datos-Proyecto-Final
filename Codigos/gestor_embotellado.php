<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php"); 
    exit;
}
require 'db.php'; 

$mensaje = "";
$error_ocurrido = false;
$lotes_aptos = []; 
$productos = [];

// Registrar embotellado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_lote = filter_input(INPUT_POST, 'id_lote', FILTER_VALIDATE_INT);
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $cantidad_botellas = filter_input(INPUT_POST, 'cantidad_botellas', FILTER_VALIDATE_INT);

    $litros_a_usar = $cantidad_botellas * 0.75; 

    if ($id_lote === false || $id_producto === false || $cantidad_botellas === false || $cantidad_botellas <= 0) {
        $error_ocurrido = true;
        $mensaje = "Error: Lote, Producto y Cantidad de Botellas (> 0) son obligatorios.";
    } else {
        $sql = 'BEGIN CA_EA_ST_IY_SP_GESTION_EMBOTELLADO(:p_lote, :p_producto, :p_cant); END;';
        $stmt = oci_parse($conn, $sql);
        
        oci_bind_by_name($stmt, ':p_lote', $id_lote);
        oci_bind_by_name($stmt, ':p_producto', $id_producto);
        oci_bind_by_name($stmt, ':p_cant', $cantidad_botellas);
        
        if (@oci_execute($stmt)) {
            $mensaje = "Embotellado registrado con exito. Se produjeron " . number_format($cantidad_botellas, 0, '', '.') . " unidades del producto. Se descontaron " . number_format($litros_a_usar, 2, ',', '.') . " litros del Lote #$id_lote.";
        } else {
            $e = oci_error($stmt);
            $error_ocurrido = true;
            $mensaje = "Error: " . mensaje_error($e['message']);

            if (strpos($e['message'], 'constraint') !== false) {
                 $mensaje = "Error: El lote no tiene suficientes litros para producir esa cantidad de botellas.";
            }
        }
        oci_free_statement($stmt);
    }
    
    header('Location: gestor_embotellado.php?mensaje=' . urlencode($mensaje) . '&error=' . ($error_ocurrido ? '1' : '0'));
    exit;
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $error_ocurrido = $_GET['error'] == '1';
}

// Cargar lotes y productos
$lotes_aptos = consultar_cursor($conn, 'CA_EA_ST_IY_SP_LISTAR_LOTES_EMBOTELLADO');
$productos = consultar_cursor($conn, 'CA_EA_ST_IY_SP_CRUD_LISTAR_PRODUCTOS');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de Embotellado</title>
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
                Gestor de Embotellado y Stock Final
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <a href="dashboard.php" class="btn btn-secondary mb-3">&larr; Volver al Panel</a>
        
        <h2 class="mb-4">Registro de Embotellado</h2>

        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $error_ocurrido ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">Convertir Lote en Producto Final</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_lote" class="form-label">Lote de Origen (Vino a granel):</label>
                            <select id="id_lote" name="id_lote" class="form-select" required>
                                <option value="">Seleccione Lote Apto</option>
                                <?php foreach ($lotes_aptos as $lote): ?>
                                    <option value="<?php echo $lote['ID_LOTE']; ?>" 
                                            title="Estado: <?php echo htmlspecialchars($lote['ESTADO']); ?>">
                                        Lote #<?php echo htmlspecialchars($lote['ID_LOTE']); ?> | <?php echo htmlspecialchars($lote['NOMBRE_VARIEDAD']); ?> (<?php echo number_format((float)$lote['LITROS_ACTUALES'], 2, ',', '.'); ?> L)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo se muestran lotes en Crianza o Finalizados.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="id_producto" class="form-label">Producto Final:</label>
                            <select id="id_producto" name="id_producto" class="form-select" required>
                                <option value="">Seleccione Producto</option>
                                <?php foreach ($productos as $producto): ?>
                                    <option value="<?php echo $producto['ID_PRODUCTO']; ?>">
                                        <?php echo htmlspecialchars($producto['NOMBRE_PROD']); ?> (Precio: $<?php echo number_format((float)$producto['PRECIO'], 0, ',', '.'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cantidad_botellas" class="form-label">Cantidad de Botellas a Producir (Unidades):</label>
                        <input type="number" min="1" id="cantidad_botellas" name="cantidad_botellas" class="form-control" required>
                        <div class="form-text">Cada unidad consume 0.75 Litros del Lote.</div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 mt-3" <?php echo empty($lotes_aptos) || empty($productos) ? 'disabled' : ''; ?>>
                        Comenzar Embotellado y Actualizar Stock
                    </button>
                    <?php if (empty($lotes_aptos) || empty($productos)): ?>
                        <p class="text-danger mt-2">No se puede embotellar si no hay lotes aptos o productos definidos.</p>
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