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

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de multa no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               l.NumeroLicencia, l.TipoLicencia, l.FechaExpedicion as FechaExpedicionLic, l.Vigencia as FechaVencimientoLic,
               c.Nombre as NombreConductor, c.CURP, c.RFC, c.Telefono, c.CorreoElectronico, 
               o.Nombre as NombreOficial, o.NumeroIdentificacion as NumeroIdentificacionOficial, o.Cargo,
               cv.Nombre as CentroVerificacionNombre,
               tc.Placas, tc.FechaExpedicion as FechaExpedicionTC,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor, 
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio, d.Estado, d.CodigoPostal
        FROM multas m
        LEFT JOIN licencias l ON m.ID_Licencia = l.ID_Licencia
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN domicilios d ON c.ID_Domicilio = d.ID_Domicilio
        LEFT JOIN oficiales o ON m.ID_Oficial = o.ID_Oficial
        LEFT JOIN centrosverificacion cv ON o.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON m.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        WHERE m.ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $multa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$multa) {
        $_SESSION['error'] = "La multa no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la multa: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pagos 
        WHERE ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $tienePago = ($stmt->fetchColumn() > 0);
} catch (PDOException $e) {
    $tienePago = false;
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Multa #<?php echo $id; ?></h1>
        <div>
      <a href="generate_xml.php?id=<?php echo $id; ?>" class="btn btn-success me-2" target="_blank">
                <i class="bi bi-file-earmark-code"></i> Generar XML
            </button>
            <a href="generate_pdf.php?id=<?php echo $id; ?>" class="btn btn-danger me-2" target="_blank">
                <i class="bi bi-file-pdf"></i> Generar PDF
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Estado de pago -->
    <div class="row mb-4">
        <div class="col-12">
            <?php if ($tienePago): ?>
            <div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                <div>
                    <strong>MULTA PAGADA</strong> - Esta multa ha sido liquidada.
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <strong>MULTA PENDIENTE DE PAGO</strong> - Esta multa aún no ha sido liquidada.
                    </div>
                    <a href="../pagos/create.php?multa_id=<?php echo $id; ?>" class="btn btn-primary ms-3">
                        <i class="bi bi-cash"></i> Registrar Pago
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Información de la Multa -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Información de la Multa</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Fecha:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars(date('d/m/Y', strtotime($multa['Fecha']))); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Importe:</div>
                        <div class="col-sm-8">
                            <span class="badge bg-danger fs-6">$<?php echo number_format($multa['Importe'], 2); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 fw-bold">Motivo:</div>
                        <div class="col-sm-8">
                            <div class="p-2 bg-light border rounded">
                                <?php echo nl2br(htmlspecialchars($multa['Motivo'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Oficial:</div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($multa['NombreOficial']); ?><br>
                            <small class="text-muted">ID: <?php echo htmlspecialchars($multa['NumeroIdentificacionOficial']); ?></small><br>
                            <small class="text-muted">Cargo: <?php echo htmlspecialchars($multa['Cargo']); ?></small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 fw-bold">Centro:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($multa['CentroVerificacionNombre'] ?? 'No especificado'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Conductor -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Información del Conductor</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Nombre:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($multa['NombreConductor'] ?? 'No especificado'); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">CURP:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($multa['CURP'] ?? 'No especificado'); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">RFC:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($multa['RFC'] ?? 'No especificado'); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Licencia:</div>
                        <div class="col-sm-8">
                            <?php echo htmlspecialchars($multa['NumeroLicencia'] ?? 'No especificado'); ?>
                            <?php if (!empty($multa['TipoLicencia'])): ?>
                                <span class="badge bg-secondary ms-2">Tipo <?php echo htmlspecialchars($multa['TipoLicencia']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Contacto:</div>
                        <div class="col-sm-8">
                            <?php if (!empty($multa['Telefono'])): ?>
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($multa['Telefono']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($multa['CorreoElectronico'])): ?>
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($multa['CorreoElectronico']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 fw-bold">Domicilio:</div>
                        <div class="col-sm-8">
                            <?php 
                                $domicilio = '';
                                if (!empty($multa['Calle'])) {
                                    $domicilio .= $multa['Calle'];
                                    if (!empty($multa['NumeroExterior'])) {
                                        $domicilio .= ' #' . $multa['NumeroExterior'];
                                    }
                                    if (!empty($multa['NumeroInterior'])) {
                                        $domicilio .= ', Int. ' . $multa['NumeroInterior'];
                                    }
                                    if (!empty($multa['Colonia'])) {
                                        $domicilio .= ', ' . $multa['Colonia'];
                                    }
                                    if (!empty($multa['Municipio'])) {
                                        $domicilio .= ', ' . $multa['Municipio'];
                                    }
                                    if (!empty($multa['Estado'])) {
                                        $domicilio .= ', ' . $multa['Estado'];
                                    }
                                    if (!empty($multa['CodigoPostal'])) {
                                        $domicilio .= ' CP. ' . $multa['CodigoPostal'];
                                    }
                                    echo htmlspecialchars($domicilio);
                                } else {
                                    echo 'No especificado';
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Vehículo -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Información del Vehículo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Placas:</div>
                                <div class="col-sm-8">
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($multa['Placas'] ?? 'No especificado'); ?></span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Vehículo:</div>
                                <div class="col-sm-8">
                                    <?php 
                                        $vehiculo = '';
                                        if (!empty($multa['Marca']) && !empty($multa['Modelo'])) {
                                            $vehiculo = $multa['Marca'] . ' ' . $multa['Modelo'];
                                            if (!empty($multa['AnoFabricacion'])) {
                                                $vehiculo .= ' (' . $multa['AnoFabricacion'] . ')';
                                            }
                                            echo htmlspecialchars($vehiculo);
                                        } else {
                                            echo 'No especificado';
                                        }
                                    ?>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Color:</div>
                                <div class="col-sm-8">
                                    <?php if (!empty($multa['Color'])): ?>
                                    <div class="d-flex align-items-center">
                                        <div style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($multa['Color']); ?>; border: 1px solid #ddd; margin-right: 10px;"></div>
                                        <?php echo htmlspecialchars($multa['Color']); ?>
                                    </div>
                                    <?php else: ?>
                                    No especificado
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Núm. Serie:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($multa['NumeroSerie'] ?? 'No especificado'); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Núm. Motor:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($multa['NumeroMotor'] ?? 'No especificado'); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">T. Circulación:</div>
                                <div class="col-sm-8">
                                    <?php if (!empty($multa['FechaExpedicionTC'])): ?>
                                    Exp: <?php echo htmlspecialchars(date('d/m/Y', strtotime($multa['FechaExpedicionTC']))); ?>
                                    <?php else: ?>
                                    No especificado
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-end">
            <?php if (!$tienePago): ?>
            <a href="../pagos/create.php?multa_id=<?php echo $id; ?>" class="btn btn-success me-2">
                <i class="bi bi-cash"></i> Registrar Pago
            </a>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Editar Multa
            </a>
            <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i> Eliminar Multa
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
            </a>
        </div>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la multa con ID <strong><?php echo $id; ?></strong>?</p>
                    <?php if ($tienePago): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> Esta multa ya tiene pagos asociados. Al eliminarla podría crear inconsistencias en el sistema.
                    </div>
                    <?php endif; ?>
                    <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css" media="print">
    @media print {
        .btn, .no-print, .modal, .alert-warning .btn {
            display: none !important;
        }
        
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        
        .card-header {
            background-color: #f8f9fa !important;
            color: #000 !important;
            border-bottom: 1px solid #ddd !important;
        }
        
        .alert-warning, .alert-success {
            border: 1px solid #ddd !important;
            background-color: #f8f9fa !important;
            color: #000 !important;
        }
        
        body {
            font-size: 12pt;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0;
        }
    }
</style>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>