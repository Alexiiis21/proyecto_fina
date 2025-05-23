<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (function_exists('redirect_if_not_authenticated')) {
    redirect_if_not_authenticated('/auth/login.php');
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de multa no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               l.NumeroLicencia, l.TipoLicencia,
               c.Nombre AS NombreConductor, c.CorreoElectronico, c.Telefono,
               o.Nombre AS NombreOficial, o.NumeroIdentificacion AS NumeroPlaca,    
               tc.Placas, tc.FechaExpedicion AS FechaExpedicionTC,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroMotor, v.NumeroSerie
        FROM multas m
        LEFT JOIN licencias l ON m.ID_Licencia = l.ID_Licencia
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN oficiales o ON m.ID_Oficial = o.ID_Oficial
        LEFT JOIN tarjetascirculacion tc ON m.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        WHERE m.ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $multa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$multa) {
        $_SESSION['error'] = "La multa solicitada no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la multa: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Detalles de Multa</h1>
        <div>
            <button onclick="window.print();" class="btn btn-primary me-2">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h3 class="card-title mb-0">Multa #<?php echo htmlspecialchars($multa['ID_Multa']); ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-3">Información de la Multa</h4>
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" style="width: 40%;">Fecha de Emisión:</th>
                            <td><?php echo date('d/m/Y', strtotime($multa['Fecha'])); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Motivo:</th>
                            <td><?php echo htmlspecialchars($multa['Motivo']); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Importe:</th>
                            <td class="text-danger fw-bold">$<?php echo number_format($multa['Importe'], 2); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Oficial:</th>
                            <td>
                                <?php echo htmlspecialchars($multa['NombreOficial']); ?>
                                <br>
                                <small class="text-muted">Placa: <?php echo htmlspecialchars($multa['NumeroPlaca']); ?></small>
                            </td>
                        </tr>
                    </table>
                    
                    <h4 class="mt-4 mb-3">Datos del Conductor</h4>
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" style="width: 40%;">Nombre:</th>
                            <td><?php echo htmlspecialchars($multa['NombreConductor'] ?? 'No disponible'); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Licencia:</th>
                            <td>
                                <?php echo htmlspecialchars($multa['NumeroLicencia'] ?? 'No disponible'); ?>
                                <?php if (!empty($multa['TipoLicencia'])): ?>
                                    <span class="badge bg-info ms-2">Tipo <?php echo htmlspecialchars($multa['TipoLicencia']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Teléfono:</th>
                            <td><?php echo htmlspecialchars($multa['Telefono'] ?? 'No disponible'); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Correo electrónico:</th>
                            <td><?php echo htmlspecialchars($multa['CorreoElectronico'] ?? 'No disponible'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h4 class="mb-3">Datos del Vehículo</h4>
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" style="width: 40%;">Placas:</th>
                            <td><?php echo htmlspecialchars($multa['Placas'] ?? 'No disponible'); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Tarjeta Circulación:</th>
                            <td>
                                ID: <?php echo htmlspecialchars($multa['ID_Tarjeta_Circulacion']); ?>
                                <?php if (!empty($multa['FechaExpedicionTC'])): ?>
                                    <br>
                                    <small class="text-muted">Expedición: <?php echo date('d/m/Y', strtotime($multa['FechaExpedicionTC'])); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Marca y Modelo:</th>
                            <td>
                                <?php 
                                    $vehiculo = '';
                                    if (!empty($multa['Marca']) && !empty($multa['Modelo'])) {
                                        $vehiculo = $multa['Marca'] . ' ' . $multa['Modelo'];
                                        if (!empty($multa['AnoFabricacion'])) {
                                            $vehiculo .= ' (' . $multa['AnoFabricacion'] . ')';
                                        }
                                        echo htmlspecialchars($vehiculo);
                                    } else {
                                        echo '<span class="text-muted">No disponible</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Color:</th>
                            <td>
                                <?php if (!empty($multa['Color'])): ?>
                                    <div class="d-flex align-items-center">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($multa['Color']); ?>; border: 1px solid #ddd; margin-right: 10px;"></div>
                                        <?php echo htmlspecialchars($multa['Color']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light">Número de Motor:</th>
                            <td><?php echo htmlspecialchars($multa['NumeroMotor'] ?? 'No disponible'); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Número de Serie:</th>
                            <td><?php echo htmlspecialchars($multa['NumeroSerie'] ?? 'No disponible'); ?></td>
                        </tr>
                    </table>
                    
                    <div class="card mt-4 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Estado de Pago</h5>
                            
                            <?php
                            // Aquí podrías añadir lógica para verificar si la multa está pagada
                            // Por ahora, simularemos que no está pagada
                            $estaPagada = false;
                            ?>
                            
                            <?php if ($estaPagada): ?>
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    Esta multa ha sido pagada.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                                    Esta multa está pendiente de pago.
                                </div>
                                <a href="../pagos/create.php?multa_id=<?php echo $id; ?>" class="btn btn-primary">
                                    <i class="bi bi-cash"></i> Registrar Pago
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-muted">
            <div class="d-flex justify-content-between">
                <span>ID: <?php echo $multa['ID_Multa']; ?></span>
                <span>Registrada en el sistema el: <?php echo date('d/m/Y', strtotime($multa['Fecha'])); ?></span>
            </div>
        </div>
    </div>
</div>

<style type="text/css" media="print">
    @media print {
        .btn, .no-print {
            display: none !important;
        }
        
        .card {
            border: none !important;
        }
        
        .card-header {
            background-color: 
            color: black !important;
        }
    }
</style>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>