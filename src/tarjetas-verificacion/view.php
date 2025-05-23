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
    $_SESSION['error'] = "ID de tarjeta de verificación no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos completos de la tarjeta
try {
    $stmt = $pdo->prepare("
        SELECT tv.*, 
               v.Marca, v.Modelo, v.Placas, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor,
               v.TipoCarroceria, v.NumeroAsientos, v.Cilindraje, v.TipoCombustible, v.Uso, v.Transmision, v.NumeroPuertas, v.Clase,
               cv.Nombre as CentroVerificacionNombre, cv.Direccion as CentroVerificacionDireccion, 
               cv.NumeroCentroVerificacion,
               tc.Placas as PlacasCirculacion, tc.FechaExpedicion as FechaExpedicionTC, tc.FechaVencimiento as FechaVencimientoTC
        FROM tarjetasverificacion tv
        LEFT JOIN vehiculos v ON tv.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN centrosverificacion cv ON tv.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON tv.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        WHERE tv.ID_Tarjeta_Verificacion = ?
    ");
    $stmt->execute([$id]);
    $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarjeta) {
        $_SESSION['error'] = "La tarjeta de verificación no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la tarjeta: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Tarjeta de Verificación</h1>
        <div>
            <a href="generate_xml.php?id=<?php echo $id; ?>" class="btn btn-success me-2" target="_blank">
                <i class="bi bi-file-earmark-code"></i> Generar XML
            </a>
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

    <div class="row">
        <!-- Información de la Tarjeta -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Información de la Tarjeta de Verificación</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Folio Certificado:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Motivo:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['MotivoVerificacion']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Fecha de Expedición:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['FechaExpedicion']))); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Hora Entrada:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars(date('H:i', strtotime($tarjeta['HoraEntrada']))); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Hora Salida:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars(date('H:i', strtotime($tarjeta['HoraSalida']))); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Vigencia:</div>
                        <div class="col-sm-8">
                            <?php 
                                echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['Vigencia'])));
                                $hoy = date('Y-m-d');
                                $vigencia = $tarjeta['Vigencia'];
                                $diasRestantes = (strtotime($vigencia) - strtotime($hoy)) / (60 * 60 * 24);
                                
                                if ($hoy <= $vigencia) {
                                    $badge = ($diasRestantes <= 30) ? 'warning' : 'success';
                                    $estado = ($diasRestantes <= 30) ? 'Por vencer' : 'Vigente';
                                } else {
                                    $badge = 'danger';
                                    $estado = 'Vencida';
                                }
                                
                                echo ' <span class="badge bg-' . $badge . '">' . $estado . '</span>';
                            ?>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Número de Serie:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroSerieVehiculo']); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 fw-bold">Tarjeta Circulación:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['PlacasCirculacion']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Centro de Verificación -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Centro de Verificación</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Nombre:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['CentroVerificacionNombre']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Número Centro:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroCentroVerificacion']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Dirección:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['CentroVerificacionDireccion']); ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-4 fw-bold">Teléfono:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['CentroVerificacionTelefono']); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 fw-bold">Email:</div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['CentroVerificacionEmail']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Vehículo -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Información del Vehículo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Placas:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['Placas']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Marca:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['Marca']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Modelo:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['Modelo']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Año:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['AnoFabricacion']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Color:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['Color']); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4 fw-bold">Tipo:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['TipoCarroceria']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Núm. Serie:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroSerie']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Núm. Motor:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroMotor']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Combustible:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['TipoCombustible']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Asientos:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroAsientos']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 fw-bold">Puertas:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['NumeroPuertas']); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4 fw-bold">Uso:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($tarjeta['Uso']); ?></div>
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
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Editar Tarjeta
            </a>
            <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i> Eliminar Tarjeta
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
                    ¿Está seguro que desea eliminar la tarjeta de verificación con folio <strong><?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?></strong>?
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