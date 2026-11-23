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
                        <p class="card-text">Gestion de datos maestros.</p>
                        <div class="d-grid gap-1">
                            <a href="mantenedor_contenedores.php" class="btn btn-sm btn-outline-primary text-start">Contenedores</a>
                            <a href="mantenedor_personal.php" class="btn btn-sm btn-outline-primary text-start">Personal</a>
                            <a href="mantenedor_insumos.php" class="btn btn-sm btn-outline-primary text-start">Insumos</a>
                            <a href="mantenedor_productos.php" class="btn btn-sm btn-outline-primary text-start">Productos</a>
                            <a href="mantenedor_clientes.php" class="btn btn-sm btn-outline-primary text-start">Clientes</a>
                            <a href="mantenedor_proveedores.php" class="btn btn-sm btn-outline-primary text-start">Proveedores</a>
                            <a href="mantenedor_variedades.php" class="btn btn-sm btn-outline-primary text-start">Variedades de Uva</a>
                            <a href="mantenedor_cuarteles.php" class="btn btn-sm btn-outline-primary text-start">Cuarteles</a>
                            <a href="mantenedor_cargos.php" class="btn btn-sm btn-outline-primary text-start">Cargos</a>
                            <a href="mantenedor_usuarios.php" class="btn btn-sm btn-outline-primary text-start">Usuarios</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success text-white">Gestores de Proceso</div>
                    <div class="card-body">
                        <p class="card-text">Operaciones de bodega, vendimia y venta.</p>
                        <div class="d-grid gap-2">
                            <a href="gestor_recepcion.php" class="btn btn-outline-success">Recepcion Vendimia</a>
                            <a href="gestor_trasvasijado.php" class="btn btn-outline-success">Gestion Trasvasijado</a>
                            <a href="gestor_aplicacion_insumo.php" class="btn btn-outline-success">Aplicacion de Insumos</a>
                            <a href="gestor_embotellado.php" class="btn btn-outline-success">Gestion Embotellado</a>
                            <a href="gestor_pedido.php" class="btn btn-outline-success">Crear Pedido de Venta</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">Reportes del Sistema</div>
                    <div class="card-body">
                        <p class="card-text">Consultas de trazabilidad, stock y auditoria.</p>
                        <div class="d-grid gap-2">
                            <a href="reporte.php#trazabilidad" class="btn btn-outline-warning text-dark">Trazabilidad Completa</a>
                            <a href="reporte.php#stock" class="btn btn-outline-warning text-dark">Alertas Stock Critico</a>
                            <a href="reporte.php#auditoria" class="btn btn-outline-warning text-dark">Log de Auditoria</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>