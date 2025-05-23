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
    $_SESSION['error'] = "ID de pago no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos completos del pago
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               tc.Placas, tc.FechaExpedicion as TC_FechaExpedicion, tc.FechaVencimiento as TC_FechaVencimiento,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor, v.TipoCarroceria,
               pr.Nombre as PropietarioNombre, pr.RFC, pr.CURP, pr.Telefono, pr.CorreoElectronico,
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio, d.Estado, d.CodigoPostal
        FROM pagos p
        LEFT JOIN tarjetascirculacion tc ON p.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios pr ON tc.ID_Propietario = pr.ID_Propietario
        LEFT JOIN domicilios d ON pr.ID_Domicilio = d.ID_Domicilio
        WHERE p.ID_Pago = ?
    ");
    $stmt->execute([$id]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pago) {
        $_SESSION['error'] = "El pago no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos del pago: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Detalles del Pago</h1>
        <div>
            <a href="generate_pdf.php?id=<?php echo $id; ?>" class="btn btn-danger me-2" target="_blank">
                <i class="bi bi-file-pdf"></i> Generar PDF
            </a>
            <a href="generate_xml.php?id=<?php echo $id; ?>" class="btn btn-success me-2" target="_blank">
                <i class="bi bi-file-earmark-code"></i> Generar XML
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

    <div class="row">
        <!-- Información del Pago -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Información del Pago</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Número de Transacción:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></div>
                    </div>
                    <?php if (!empty($pago['LineaCaptura'])): ?>
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Línea de Captura:</div>
                        <div class="col-sm-7"><?php echo htmlspecialchars($pago['LineaCaptura']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Fecha Límite:</div>
                        <div class="col-sm-7"><?php echo date('d/m/Y', strtotime($pago['FechaLimite'])); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Fecha de Pago:</div>
                        <div class="col-sm-7">
                            <?php 
                                echo date('d/m/Y', strtotime($pago['FechaPago']));
                                
                                // Determinar si el pago fue a tiempo o tardío
                                $fechaPago = new DateTime($pago['FechaPago']);
                                $fechaLimite = new DateTime($pago['FechaLimite']);
                                
                                if ($fechaPago > $fechaLimite) {
                                    $diff = $fechaPago->diff($fechaLimite);
                                    echo ' <span class="badge bg-warning">Tardío (' . $diff->days . ' días)</span>';
                                } else {
                                    echo ' <span class="badge bg-success">A tiempo</span>';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Importe:</div>
                        <div class="col-sm-7">
                            <span class="text-success fw-bold">$<?php echo number_format($pago['Importe'], 2); ?></span>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-5 fw-bold">Método de Pago:</div>
                        <div class="col-sm-7">
                            <?php 
                                $badgeClass = 'secondary';
                                switch ($pago['MetodoPago']) {
                                    case 'EFECTIVO':
                                        $badgeClass = 'success';
                                        break;
                                    case 'TARJETA':
                                        $badgeClass = 'primary';
                                        break;
                                    case 'TRANSFERENCIA':
                                        $badgeClass = 'info';
                                        break;
                                    case 'CHEQUE':
                                        $badgeClass = 'warning';
                                        break;
                                }
                            ?>
                            <span class="badge bg-<?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($pago['MetodoPago']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de la Tarjeta de Circulación -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Tarjeta de Circulación</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Placas:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($pago['Placas'] ?? 'No disponible'); ?></div>
                    </div>
                    <?php if (!empty($pago['TC_FechaExpedicion'])): ?>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Expedición:</div>
                        <div class="col-sm-8"><?php echo date('d/m/Y', strtotime($pago['TC_FechaExpedicion'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pago['TC_FechaVencimiento'])): ?>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Vencimiento:</div>
                        <div class="col-sm-8">
                            <?php 
                                echo date('d/m/Y', strtotime($pago['TC_FechaVencimiento']));
                                $hoy = date('Y-m-d');
                                $vencimiento = $pago['TC_FechaVencimiento'];
                                
                                if ($hoy > $vencimiento) {
                                    echo ' <span class="badge bg-danger">Vencida</span>';
                                } else {
                                    $diasRestantes = (strtotime($vencimiento) - strtotime($hoy)) / (60 * 60 * 24);
                                    if ($diasRestantes <= 30) {
                                        echo ' <span class="badge bg-warning">Por vencer</span>';
                                    } else {
                                        echo ' <span class="badge bg-success">Vigente</span>';
                                    }
                                }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Vehículo:</div>
                        <div class="col-sm-8">
                            <?php 
                                $vehiculo = '';
                                if (!empty($pago['Marca']) && !empty($pago['Modelo'])) {
                                    $vehiculo = $pago['Marca'] . ' ' . $pago['Modelo'];
                                    if (!empty($pago['AnoFabricacion'])) {
                                        $vehiculo .= ' (' . $pago['AnoFabricacion'] . ')';
                                    }
                                } else {
                                    $vehiculo = 'No disponible';
                                }
                                echo htmlspecialchars($vehiculo);
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($pago['Color'])): ?>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Color:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($pago['Color']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pago['NumeroSerie'])): ?>
                    <div class="row">
                        <div class="col-sm-4 fw-bold">No. Serie:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($pago['NumeroSerie']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($pago['PropietarioNombre'])): ?>
                <div class="card-footer bg-light">
                    <small>
                        <span class="fw-bold">Propietario:</span> 
                        <?php echo htmlspecialchars($pago['PropietarioNombre']); ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comprobante de Pago -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Comprobante de Pago</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="display-4 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h4 class="mt-3">Pago Registrado Correctamente</h4>
                        <p class="text-muted">
                            El pago con número de transacción <strong><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></strong> 
                            ha sido procesado el día <strong><?php echo date('d/m/Y', strtotime($pago['FechaPago'])); ?></strong> 
                            por un importe de <strong>$<?php echo number_format($pago['Importe'], 2); ?></strong>
                        </p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Detalles Adicionales</h6>
                            <dl class="row">
                                <?php if (!empty($pago['LineaCaptura'])): ?>
                                <dt class="col-sm-4">Línea de Captura:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['LineaCaptura']); ?></dd>
                                <?php endif; ?>
                                
                                <dt class="col-sm-4">ID de Pago:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['ID_Pago']); ?></dd>
                                
                                <dt class="col-sm-4">Método de Pago:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['MetodoPago']); ?></dd>
                            </dl>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Información de Referencia</h6>
                            <dl class="row">
                                <dt class="col-sm-4">Tarjeta Circulación:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['Placas'] ?? 'No disponible'); ?></dd>
                                
                                <?php if (!empty($pago['PropietarioNombre'])): ?>
                                <dt class="col-sm-4">Propietario:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['PropietarioNombre']); ?></dd>
                                <?php endif; ?>
                                
                                <?php if (!empty($pago['RFC'])): ?>
                                <dt class="col-sm-4">RFC:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($pago['RFC']); ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de acción -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-end">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Editar Pago
            </a>
            <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i> Eliminar Pago
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
                    ¿Está seguro que desea eliminar el pago con número de transacción <strong><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></strong>?
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

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>