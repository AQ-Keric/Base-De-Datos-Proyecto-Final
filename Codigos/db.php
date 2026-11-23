
<?php
$username = "user_developer";
$password = "evaluacion3";

// Conexion a Oracle (contenedor Docker o localhost)
$conn = @oci_connect($username, $password, "172.17.0.2/FREEPDB1");
if (!$conn) {
    $conn = @oci_connect($username, $password, "localhost/FREEPDB1");
}

if (!$conn) {
    $e = oci_error();
    die("Error de conexion a Oracle: " . ($e['message'] ?? 'No se pudo conectar'));
}
?>
