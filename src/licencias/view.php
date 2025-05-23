<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Solo administradores pueden editar licencias
redirect_if_not_admin('/licencias/index.php');

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de licencia no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos de la licencia
try {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               c.Nombre as NombreConductor,
               c.ImagenPerfil,
               c.Firma,
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, 
               d.Municipio, d.Estado, d.CodigoPostal, d.Referencia
        FROM licencias l
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN domicilios d ON l.ID_Domicilio = d.ID_Domicilio
        WHERE l.ID_Licencia = ?
    ");
    $stmt->execute([$id]);
    $licencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licencia) {
        $_SESSION['error'] = "La licencia solicitada no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la licencia: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Detalles de Licencia</h1>
        <div>
            <a href="generate_pdf.php?id=<?php echo $id; ?>" class="btn btn-primary me-2" target="_blank">
                <i class="bi bi-file-pdf"></i> Generar PDF
            </a>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0">Licencia #<?php echo htmlspecialchars($licencia['NumeroLicencia']); ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 text-center">
                    <?php if (!empty($licencia['ImagenPerfil']) && file_exists("../uploads/fotos/" . $licencia['ImagenPerfil'])): ?>
                        <img src="../uploads/fotos/<?php echo $licencia['ImagenPerfil']; ?>" alt="Foto del conductor" class="img-fluid rounded mb-3" style="max-height: 200px;">
                    <?php else: ?>
                        <div class="bg-secondary text-white p-5 rounded mb-3">
                            <i class="bi bi-person-badge" style="font-size: 3rem;"></i>
                            <p>Sin foto</p>
                        </div>
                    <?php endif; ?>
                    
                    <h4><?php echo htmlspecialchars($licencia['NombreConductor'] ?? 'Conductor no especificado'); ?></h4>
                    
                    <?php 
                        $hoy = date('Y-m-d');
                        $vencimiento = $licencia['Vigencia'] ?? '';
                        $badgeClass = 'secondary';
                        $estadoText = 'No disponible';
                        
                        if (!empty($vencimiento)) {
                            if ($hoy > $vencimiento) {
                                $badgeClass = 'danger';
                                $estadoText = 'Vencida';
                            } else {
                                $diasRestantes = (strtotime($vencimiento) - strtotime($hoy)) / (60 * 60 * 24);
                                if ($diasRestantes <= 30) {
                                    $badgeClass = 'warning text-dark';
                                    $estadoText = 'Por vencer';
                                } else {
                                    $badgeClass = 'success';
                                    $estadoText = 'Vigente';
                                }
                            }
                        }
                    ?>
                    <div class="mb-3">
                        <span class="badge bg-<?php echo $badgeClass; ?> fs-5">
                            <?php echo $estadoText; ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Información de la Licencia</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Número de Licencia</div>
                                        <?php echo htmlspecialchars($licencia['NumeroLicencia']); ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Tipo de Licencia</div>
                                        <?php 
                                            $tipoTexto = match($licencia['TipoLicencia']) {
                                                'A' => 'Tipo A (Automovilista)',
                                                'B' => 'Tipo B (Chofer)',
                                                'C' => 'Tipo C (Motociclista)',
                                                'D' => 'Tipo D (Transporte Público)',
                                                default => $licencia['TipoLicencia']
                                            };
                                            echo htmlspecialchars($tipoTexto);
                                        ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Grupo Sanguíneo</div>
                                        <?php echo htmlspecialchars($licencia['GrupoSanguineo'] ?? 'No especificado'); ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Antigüedad</div>
                                        <?php echo htmlspecialchars($licencia['Antiguedad'] ?? '0'); ?> años
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Fechas</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Fecha de Nacimiento</div>
                                        <?php echo !empty($licencia['FechaNacimiento']) ? date('d/m/Y', strtotime($licencia['FechaNacimiento'])) : 'No disponible'; ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Fecha de Expedición</div>
                                        <?php echo !empty($licencia['FechaExpedicion']) ? date('d/m/Y', strtotime($licencia['FechaExpedicion'])) : 'No disponible'; ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Vigencia hasta</div>
                                        <?php echo !empty($licencia['Vigencia']) ? date('d/m/Y', strtotime($licencia['Vigencia'])) : 'No disponible'; ?>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">Restricciones</div>
                                        <?php echo !empty($licencia['Restricciones']) ? htmlspecialchars($licencia['Restricciones']) : 'Ninguna'; ?>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h5>Domicilio</h5>
                    <p>
                        <?php 
                            if (!empty($licencia['Calle'])) {
                                echo htmlspecialchars($licencia['Calle']);
                                if (!empty($licencia['NumeroExterior'])) {
                                    echo " #" . htmlspecialchars($licencia['NumeroExterior']);
                                }
                                if (!empty($licencia['NumeroInterior'])) {
                                    echo " Int. " . htmlspecialchars($licencia['NumeroInterior']);
                                }
                                echo ", " . htmlspecialchars($licencia['Colonia']);
                                echo ", " . htmlspecialchars($licencia['Municipio']);
                                echo ", " . htmlspecialchars($licencia['Estado']);
                                
                                if (!empty($licencia['CodigoPostal'])) {
                                    echo ", C.P. " . htmlspecialchars($licencia['CodigoPostal']);
                                }
                            } else {
                                echo "No se ha registrado un domicilio.";
                            }
                        ?>
                    </p>
                    
                    <?php if (!empty($licencia['Referencia'])): ?>
                        <div class="mt-2">
                            <strong>Referencia:</strong> <?php echo htmlspecialchars($licencia['Referencia']); ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-3">Firma</h5>
                    <div class="border p-3 text-center">
                        <?php if (!empty($licencia['Firma']) && file_exists("../uploads/firmas/" . $licencia['Firma'])): ?>
                            <img src="../uploads/firmas/<?php echo $licencia['Firma']; ?>" alt="Firma del conductor" class="img-fluid" style="max-height: 100px;">
                        <?php else: ?>
                            <p class="text-muted mb-0">No hay firma registrada</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <small class="text-muted">ID: <?php echo $licencia['ID_Licencia']; ?></small>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>