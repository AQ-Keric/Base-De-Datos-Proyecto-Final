<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
}
require 'db.php'; 

// --- 1. FUNCIÓN AUXILIAR para ejecutar SP que retornan CURSORES ---
function ejecutar_reporte_cursor($conn, $sp_name) {
    $data = [];
    $cursor = oci_new_cursor($conn);
    $sql = 'BEGIN CA_EA_ST_IY_PKG_VINA.' . $sp_name . '(:cursor); END;'; 
    $stmt = oci_parse($conn, $sql);
    
    oci_bind_by_name($stmt, ':cursor', $cursor, -1, OCI_B_CURSOR);
    
    if (@oci_execute($stmt) && @oci_execute($cursor)) {
        while ($row = oci_fetch_assoc($cursor)) {
            $data[] = $row;
        }
    } else {
        // Manejo básico de error
    }
    oci_free_statement($stmt);
    oci_free_statement($cursor);
    return $data;
}

// --- 2. CONSULTAS A LOS REPORTES ---

// A. Trazabilidad Completa (Llamando al SP con cursor)
$trazabilidad = ejecutar_reporte_cursor($conn, 'CA_EA_ST_IY_SP_REPORTE_TRAZABILIDAD');

// B. Reporte de Stock Crítico 
$stock_critico = ejecutar_reporte_cursor($conn, 'CA_EA_ST_IY_SP_REPORTE_STOCK_CRITICO');

// C. Auditoría de Movimientos 
$sql_auditoria = "SELECT id_log, id_lote, usuario_bd, fecha_movimiento, contenedor_origen, contenedor_destino, accion
                  FROM CA_EA_ST_IY_AUDITORIA_MOVIMIENTOS ORDER BY fecha_movimiento DESC";
$stmt_auditoria = oci_parse($conn, $sql_auditoria);
@oci_execute($stmt_auditoria);
$auditoria = [];
while ($row = oci_fetch_assoc($stmt_auditoria)) {
    $auditoria[] = $row;
}
oci_free_statement($stmt_auditoria);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Gestión de Viña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .navbar-custom { background-color: #722F37; }
        .dataTables_wrapper .row:first-child { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Sistema Viña</a>
            <span class="navbar-text text-white">
                Reportes e Inteligencia de Negocio
            </span>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📊 Reportes de Gestión de Viña</h2>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Panel</a>
        </div>

        <ul class="nav nav-tabs mb-4" id="reportesTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="trazabilidad-tab" data-bs-toggle="tab" data-bs-target="#trazabilidad" type="button" role="tab" aria-controls="trazabilidad" aria-selected="true">
                    Trazabilidad Completa
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab" aria-controls="stock" aria-selected="false">
                    Alertas de Stock Crítico (<?php echo count($stock_critico); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="auditoria-tab" data-bs-toggle="tab" data-bs-target="#auditoria" type="button" role="tab" aria-controls="auditoria" aria-selected="false">
                    Log de Auditoría (Trasvasijados)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="reportesTabContent">
            
            <div class="tab-pane fade show active" id="trazabilidad" role="tabpanel" aria-labelledby="trazabilidad-tab">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Historial Completo por Lote</h4>
                        <table id="tablaTrazabilidad" class="table table-bordered table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Lote ID</th>
                                    <th>Variedad</th>
                                    <th>Origen (Cuartel)</th>
                                    <th>Fecha Cosecha</th>
                                    <th>Contenedor Actual</th>
                                    <th>Estado</th>
                                    <th>Producto Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trazabilidad as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['ID_LOTE']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['NOMBRE_VARIEDAD']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ORIGEN_CUARTEL']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($row['FECHA_COSECHA'], 0, 10)); ?></td>
                                    <td><?php echo htmlspecialchars($row['CONTENEDOR_INFO'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($row['ESTADO_VINO'] == 'Fermentacion' ? 'primary' : ($row['ESTADO_VINO'] == 'Crianza' ? 'warning text-dark' : 'success')); ?>"><?php echo htmlspecialchars($row['ESTADO_VINO']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['PRODUCTO_FINAL'] ? $row['PRODUCTO_FINAL'] : 'En proceso'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="stock" role="tabpanel" aria-labelledby="stock-tab">
                <div class="card border-danger">
                    <div class="card-body">
                        <h4 class="card-title text-danger">🚨 Insumos bajo Stock Mínimo</h4>
                        <?php if (empty($stock_critico)): ?>
                            <div class="alert alert-success">No se detectaron insumos en stock crítico.</div>
                        <?php else: ?>
                            <table id="tablaStock" class="table table-bordered table-striped">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Insumo</th>
                                        <th>Stock Actual</th>
                                        <th>Stock Mínimo</th>
                                        <th>Déficit (Unidades)</th>
                                        <th>Proveedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_critico as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['NOMBRE_INSUMO']); ?></td>
                                        <td><?php echo number_format((float)$row['STOCK_ACTUAL'], 2, ',', '.'); ?></td>
                                        <td><?php echo number_format((float)$row['STOCK_MIN'], 2, ',', '.'); ?></td>
                                        <td><?php echo number_format((float)$row['DEFICIT'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($row['PROVEEDOR']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="auditoria" role="tabpanel" aria-labelledby="auditoria-tab">
                <div class="card border-secondary">
                    <div class="card-body">
                        <h4 class="card-title">Log de Trasvasijados y Movimientos</h4>
                        <?php if (empty($auditoria)): ?>
                            <div class="alert alert-info">No hay movimientos registrados en la tabla de auditoría.</div>
                        <?php else: ?>
                            <table id="tablaAuditoria" class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID Log</th>
                                        <th>Lote</th>
                                        <th>Fecha Movimiento</th>
                                        <th>Cont. Origen</th>
                                        <th>Cont. Destino</th>
                                        <th>Acción</th>
                                        <th>Usuario BD</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditoria as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ID_LOG']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ID_LOTE']); ?></td>
                                        <td><?php echo htmlspecialchars($row['FECHA_MOVIMIENTO']); ?></td>
                                        <td><?php echo htmlspecialchars($row['CONTENEDOR_ORIGEN']); ?></td>
                                        <td><?php echo htmlspecialchars($row['CONTENEDOR_DESTINO']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ACCION']); ?></td>
                                        <td><?php echo htmlspecialchars($row['USUARIO_BD']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {

            $('#tablaTrazabilidad').DataTable({
                order: [[0, 'desc']], 
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' }
            });
            $('#tablaStock').DataTable({
                order: [[3, 'desc']], 
                paging: false,
                searching: false,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' }
            });
            $('#tablaAuditoria').DataTable({
                order: [[2, 'desc']], 
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' }
            });
        });
    </script>
</body>
</html>
<?php
oci_close($conn);
?>