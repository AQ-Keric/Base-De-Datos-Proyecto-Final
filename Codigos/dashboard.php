<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Viña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">Sistema Viña</a>
            <span class="navbar-text text-white">
                Hola, <?php echo $_SESSION['nombre']; ?> | <a href="logout.php" class="text-white fw-bold">Salir</a>
            </span>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="mb-4 text-center">Panel de Control</h2>
        <div class="row g-4">
            
            <div class="col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-header bg-primary text-white">Mantenedores</div>
                    <div class="card-body">
                        <p class="card-text">Gestión de datos maestros (CRUD).</p>
                        <div class="d-grid gap-2">
                            <a href="mantenedor_contenedores.php" class="btn btn-outline-primary">Gestión Contenedores</a>
                            <a href="mantenedor_personal.php" class="btn btn-outline-primary">Gestión Personal</a>
                            <a href="mantenedor_insumos.php" class="btn btn-outline-primary">Gestión de Insumos</a>
                            <a href="mantenedor_productos.php" class="btn btn-outline-primary">Gestión de Productos</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success text-white">Gestores de Proceso</div>
                    <div class="card-body">
                        <p class="card-text">Registrar operaciones de vendimia y bodega.</p>
                        <div class="d-grid gap-2">
                            <a href="gestor_recepcion.php" class="btn btn-outline-success">Recepción Vendimia</a>
                            <a href="gestor_trasvasijado.php" class="btn btn-outline-success">Gestion Trasvasijado</a>
                            <a href="gestor_aplicacion_insumo.php" class="btn btn-outline-success">Aplicación de Insumos</a>
                            <a href="gestor_embotellado.php" class="btn btn-outline-success">Gestión Embotellado</a>
                        </div>
                    </div>
                </div>
            </div>

            

            <div class="col-md-4">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">Reportes e Inteligencia</div>
                    <div class="card-body">
                        <p class="card-text">Visualización de datos y alertas.</p>
                        <div class="d-grid gap-2">
                            <a href="reporte.php" class="btn btn-outline-warning text-dark">Trazabilidad Completa</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>