<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.CA_EA_ST_IY_SP_LOGIN(:p_email, :p_pass, :p_cursor); END;';
    $stmt = oci_parse($conn, $sql);
    $cursor = oci_new_cursor($conn);

    oci_bind_by_name($stmt, ':p_email', $email);
    oci_bind_by_name($stmt, ':p_pass', $pass);
    oci_bind_by_name($stmt, ':p_cursor', $cursor, -1, OCI_B_CURSOR);

    oci_execute($stmt);
    oci_execute($cursor);

    if ($row = oci_fetch_assoc($cursor)) {
        $_SESSION['id_usuario'] = $row['ID_USUARIO'];
        $_SESSION['nombre'] = $row['NOMBRE'];
        $_SESSION['perfil'] = $row['ID_PERFIL'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
    
    oci_free_statement($stmt);
    oci_free_statement($cursor);
    oci_close($conn);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Viña Santa Carolina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 400px; border: none; shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-vino { background-color: #722F37; color: white; } /* Color Vino */
        .btn-vino:hover { background-color: #5a232b; color: white; }
    </style>
</head>
<body>
    <div class="card p-4">
        <div class="text-center mb-4">
            <h3 style="color: #722F37;">Viña Santa Carolina</h3>
            <p class="text-muted">Sistema de Trazabilidad</p>
        </div>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Email Corporativo</label>
                <input type="email" name="email" class="form-control" required placeholder="admin@vina.cl">
            </div>
            <div class="mb-3">
                <label>Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-vino w-100">Ingresar</button>
        </form>
    </div>
</body>
</html>