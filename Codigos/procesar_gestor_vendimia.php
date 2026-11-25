<?php

include 'db.php'; 

$id_cuartel = filter_input(INPUT_POST, 'id_cuartel', FILTER_VALIDATE_INT);
$id_estanque = filter_input(INPUT_POST, 'id_estanque', FILTER_VALIDATE_INT);
$kilos = filter_input(INPUT_POST, 'kilos', FILTER_VALIDATE_FLOAT);

$id_personal = 1; 

if ($id_cuartel === false || $id_cuartel === null ||
    $id_estanque === false || $id_estanque === null ||
    $kilos === false || $kilos <= 0) {
    
    die("Error de Validación: Los datos ingresados son inválidos o incompletos.");
}

$sql = 'BEGIN CA_EA_ST_IY_SP_GESTION_RECEPCION(:cuartel, :personal, :kilos, :contenedor); END;';

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ':cuartel', $id_cuartel);
oci_bind_by_name($stmt, ':personal', $id_personal);
oci_bind_by_name($stmt, ':kilos', $kilos);
oci_bind_by_name($stmt, ':contenedor', $id_estanque); 

$ejecucion_exitosa = @oci_execute($stmt); 

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado de Recepción</title>
</head>
<body>
    <a href="index.php">← Volver al Menú</a>
    <h2>Resultado de Recepción de Vendimia</h2>
    
    <?php
    if ($ejecucion_exitosa) {
        $litros = $kilos * 0.70; 
        echo "Recepción Registrada con Éxito";
        echo "<ul>";
        echo "<li>Cuartel (Origen): {$id_cuartel}</li>";
        echo "<li>Estanque/Contenedor (Destino): {$id_estanque}</li>";
        echo "<li>Kilos Recibidos: {$kilos} kg</li>";
        echo "<li>Litros Generados: " . number_format($litros, 2, ',', '.') . " L</li>";
        echo "</ul>";
        echo "<p>Se ha registrado la cosecha y se creó un nuevo Lote.</p>";
    } else {
        $error = oci_error($stmt);
        echo "Error al Registrar Recepción: ";
        echo "<blockquote style='color: red;'>";
        echo "Código Oracle: " . (isset($error['code']) ? $error['code'] : 'N/A') . "<br>";
        echo "Mensaje: " . (isset($error['message']) ? htmlspecialchars($error['message']) : 'Error desconocido') . "<br>";
        echo "</blockquote>";
        echo "<p>Asegúrate de que los IDs de Cuartel, Personal y Contenedor sean válidos en la base de datos.</p>";
    }

    oci_free_statement($stmt);
    oci_close($conn);
    ?>

    <hr>
    <a href="gestor_recepcion.php">Volver al Formulario de Recepción</a>

</body>
</html>