
<?php
// configuracion de la conexion a la base de datos con el metodo oci8
$username = "user_developer";
$password = "evaluacion3";
$connection_string = "localhost/FREEPDB1";

$conn = oci_connect($username, $password, $connection_string);

if (!$conn) {
    $e = oci_error();
    die("Error de conexión a Oracle: " . $e['message']);
}
?>
