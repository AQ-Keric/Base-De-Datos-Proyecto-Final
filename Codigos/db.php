
<?php
$username = "user_developer";
$password = "evaluacion3";

// Conexion a Oracle 
$conn = @oci_connect($username, $password, "172.17.0.2/FREEPDB1");
if (!$conn) {
    $conn = @oci_connect($username, $password, "localhost/FREEPDB1");
}

if (!$conn) {
    $e = oci_error();
    die("Error de conexion a Oracle: " . ($e['message'] ?? 'No se pudo conectar'));
}

// Funcion simple para obtener listas desde procedimientos con cursor
function consultar_cursor($conn, $sp_name) {
    $datos = [];
    $cursor = oci_new_cursor($conn);
    $stmt = oci_parse($conn, 'BEGIN ' . $sp_name . '(:cursor); END;');
    oci_bind_by_name($stmt, ':cursor', $cursor, -1, OCI_B_CURSOR);
    if (@oci_execute($stmt) && @oci_execute($cursor)) {
        while ($row = oci_fetch_assoc($cursor)) {
            $datos[] = $row;
        }
    }
    oci_free_statement($stmt);
    oci_free_statement($cursor);
    return $datos;
}
?>
