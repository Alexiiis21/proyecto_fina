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

// Obtener datos de la tarjeta
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM tarjetasverificacion
        WHERE ID_Tarjeta_Verificacion = ?
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

// Cargar listas para selects
try {
    // Obtener vehículos
    $stmt = $pdo->query("SELECT ID_Vehiculo, Marca, Modelo, AnoFabricacion, Placas, NumeroSerie FROM vehiculos ORDER BY Marca, Modelo");
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener centros de verificación
    $stmt = $pdo->query("SELECT ID_Centro_Verificacion, Nombre, NumeroCentroVerificacion FROM centrosverificacion ORDER BY Nombre");
    $centros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener tarjetas de circulación
    $stmt = $pdo->query("
        SELECT tc.ID_Tarjeta_Circulacion, tc.Placas, v.Marca, v.Modelo, v.AnoFabricacion 
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        ORDER BY tc.Placas
    ");
    $tarjetasCirculacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar datos: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Manejar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $tarjeta = [
        'ID_Tarjeta_Verificacion' => $id,
        'ID_Vehiculo' => isset($_POST['vehiculo']) ? trim($_POST['vehiculo']) : '',
        'ID_Centro_Verificacion' => isset($_POST['centro']) ? trim($_POST['centro']) : '',
        'FechaExpedicion' => isset($_POST['fecha_expedicion']) ? trim($_POST['fecha_expedicion']) : '',
        'HoraEntrada' => isset($_POST['hora_entrada']) ? trim($_POST['hora_entrada']) : '',
        'HoraSalida' => isset($_POST['hora_salida']) ? trim($_POST['hora_salida']) : '',
        'MotivoVerificacion' => isset($_POST['motivo']) ? trim($_POST['motivo']) : '',
        'FolioCertificado' => isset($_POST['folio']) ? trim($_POST['folio']) : '',
        'Vigencia' => isset($_POST['vigencia']) ? trim($_POST['vigencia']) : '',
        'ID_Tarjeta_Circulacion' => isset($_POST['tarjeta_circulacion']) ? trim($_POST['tarjeta_circulacion']) : '',
        'NumeroSerieVehiculo' => isset($_POST['numero_serie']) ? trim($_POST['numero_serie']) : ''
    ];
    
    // Validar datos
    $errores = [];
    
    if (empty($tarjeta['ID_Vehiculo'])) {
        $errores[] = "Debe seleccionar un vehículo.";
    }
    
    if (empty($tarjeta['ID_Centro_Verificacion'])) {
        $errores[] = "Debe seleccionar un centro de verificación.";
    }
    
    if (empty($tarjeta['FechaExpedicion'])) {
        $errores[] = "La fecha de expedición es obligatoria.";
    }
    
    if (empty($tarjeta['HoraEntrada'])) {
        $errores[] = "La hora de entrada es obligatoria.";
    }
    
    if (empty($tarjeta['HoraSalida'])) {
        $errores[] = "La hora de salida es obligatoria.";
    } else if ($tarjeta['HoraSalida'] <= $tarjeta['HoraEntrada']) {
        $errores[] = "La hora de salida debe ser posterior a la hora de entrada.";
    }
    
    if (empty($tarjeta['MotivoVerificacion'])) {
        $errores[] = "El motivo de verificación es obligatorio.";
    }
    
    if (empty($tarjeta['FolioCertificado'])) {
        $errores[] = "El folio del certificado es obligatorio.";
    } else {
        // Verificar si el folio ya existe en otra tarjeta
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tarjetasverificacion WHERE FolioCertificado = ? AND ID_Tarjeta_Verificacion != ?");
        $stmt->execute([$tarjeta['FolioCertificado'], $id]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "El folio de certificado ya existe en otra tarjeta. Por favor, use otro.";
        }
    }
    
    if (empty($tarjeta['Vigencia'])) {
        $errores[] = "La vigencia es obligatoria.";
    } else if ($tarjeta['Vigencia'] <= $tarjeta['FechaExpedicion']) {
        $errores[] = "La fecha de vigencia debe ser posterior a la fecha de expedición.";
    }
    
    if (empty($tarjeta['ID_Tarjeta_Circulacion'])) {
        $errores[] = "Debe seleccionar una tarjeta de circulación.";
    }
    
    if (empty($tarjeta['NumeroSerieVehiculo'])) {
        $errores[] = "El número de serie del vehículo es obligatorio.";
    }
    
    // Si no hay errores, actualizar en la base de datos
    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tarjetasverificacion SET
                    ID_Vehiculo = ?,
                    ID_Centro_Verificacion = ?,
                    FechaExpedicion = ?,
                    HoraEntrada = ?,
                    HoraSalida = ?,
                    MotivoVerificacion = ?,
                    FolioCertificado = ?,
                    Vigencia = ?,
                    ID_Tarjeta_Circulacion = ?,
                    NumeroSerieVehiculo = ?
                WHERE ID_Tarjeta_Verificacion = ?
            ");
            
            $result = $stmt->execute([
                $tarjeta['ID_Vehiculo'],
                $tarjeta['ID_Centro_Verificacion'],
                $tarjeta['FechaExpedicion'],
                $tarjeta['HoraEntrada'],
                $tarjeta['HoraSalida'],
                $tarjeta['MotivoVerificacion'],
                $tarjeta['FolioCertificado'],
                $tarjeta['Vigencia'],
                $tarjeta['ID_Tarjeta_Circulacion'],
                $tarjeta['NumeroSerieVehiculo'],
                $id
            ]);
            
            if ($result) {
                $_SESSION['success'] = "Tarjeta de verificación actualizada correctamente.";
                header("Location: view.php?id=$id");
                exit;
            } else {
                $_SESSION['error'] = "Error al actualizar la tarjeta de verificación.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Tarjeta de Verificación</h1>
        <div>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-info me-2">
                <i class="bi bi-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="card-title border-bottom pb-2">Datos de la Verificación</h5>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="folio" class="form-label">Folio del Certificado *</label>
                        <input type="text" class="form-control" id="folio" name="folio" value="<?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?>" required>
                        <div class="form-text">Número único de identificación del certificado</div>
                    </div>
                    <div class="col-md-6">
                        <label for="centro" class="form-label">Centro de Verificación *</label>
                        <select class="form-select" id="centro" name="centro" required>
                            <option value="">Seleccione un centro</option>
                            <?php foreach ($centros as $centro): ?>
                                <option value="<?php echo $centro['ID_Centro_Verificacion']; ?>" <?php if ($tarjeta['ID_Centro_Verificacion'] == $centro['ID_Centro_Verificacion']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($centro['Nombre'] . ' (' . $centro['NumeroCentroVerificacion'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="fecha_expedicion" class="form-label">Fecha de Expedición *</label>
                        <input type="date" class="form-control" id="fecha_expedicion" name="fecha_expedicion" value="<?php echo htmlspecialchars($tarjeta['FechaExpedicion']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hora_entrada" class="form-label">Hora de Entrada *</label>
                        <input type="time" class="form-control" id="hora_entrada" name="hora_entrada" value="<?php echo htmlspecialchars($tarjeta['HoraEntrada']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="hora_salida" class="form-label">Hora de Salida *</label>
                        <input type="time" class="form-control" id="hora_salida" name="hora_salida" value="<?php echo htmlspecialchars($tarjeta['HoraSalida']); ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="motivo" class="form-label">Motivo de Verificación *</label>
                        <select class="form-select" id="motivo" name="motivo" required>
                            <option value="">Seleccione un motivo</option>
                            <option value="Verificación Semestral" <?php if ($tarjeta['MotivoVerificacion'] == 'Verificación Semestral') echo 'selected'; ?>>Verificación Semestral</option>
                            <option value="Alta de Vehículo" <?php if ($tarjeta['MotivoVerificacion'] == 'Alta de Vehículo') echo 'selected'; ?>>Alta de Vehículo</option>
                            <option value="Cambio de Propietario" <?php if ($tarjeta['MotivoVerificacion'] == 'Cambio de Propietario') echo 'selected'; ?>>Cambio de Propietario</option>
                            <option value="Pérdida o Robo" <?php if ($tarjeta['MotivoVerificacion'] == 'Pérdida o Robo') echo 'selected'; ?>>Pérdida o Robo</option>
                            <option value="Modificación Técnica" <?php if ($tarjeta['MotivoVerificacion'] == 'Modificación Técnica') echo 'selected'; ?>>Modificación Técnica</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="vigencia" class="form-label">Vigencia *</label>
                        <input type="date" class="form-control" id="vigencia" name="vigencia" value="<?php echo htmlspecialchars($tarjeta['Vigencia']); ?>" required>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="card-title border-bottom pb-2 mt-3">Datos del Vehículo</h5>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="vehiculo" class="form-label">Vehículo *</label>
                        <select class="form-select" id="vehiculo" name="vehiculo" required>
                            <option value="">Seleccione un vehículo</option>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                                <option value="<?php echo $vehiculo['ID_Vehiculo']; ?>" 
                                        data-serie="<?php echo htmlspecialchars($vehiculo['NumeroSerie']); ?>"
                                        <?php if ($tarjeta['ID_Vehiculo'] == $vehiculo['ID_Vehiculo']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($vehiculo['Marca'] . ' ' . $vehiculo['Modelo'] . ' (' . $vehiculo['AnoFabricacion'] . ') - ' . $vehiculo['Placas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="numero_serie" class="form-label">Número de Serie del Vehículo *</label>
                        <input type="text" class="form-control" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($tarjeta['NumeroSerieVehiculo']); ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="tarjeta_circulacion" class="form-label">Tarjeta de Circulación *</label>
                        <select class="form-select" id="tarjeta_circulacion" name="tarjeta_circulacion" required>
                            <option value="">Seleccione una tarjeta de circulación</option>
                            <?php foreach ($tarjetasCirculacion as $tc): ?>
                                <option value="<?php echo $tc['ID_Tarjeta_Circulacion']; ?>" <?php if ($tarjeta['ID_Tarjeta_Circulacion'] == $tc['ID_Tarjeta_Circulacion']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars('Placas: ' . $tc['Placas'] . ' - ' . $tc['Marca'] . ' ' . $tc['Modelo'] . ' (' . $tc['AnoFabricacion'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-rellenar número de serie cuando se selecciona un vehículo
    const vehiculoSelect = document.getElementById('vehiculo');
    const numeroSerieInput = document.getElementById('numero_serie');
    
    vehiculoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            numeroSerieInput.value = selectedOption.dataset.serie || '';
        } else {
            numeroSerieInput.value = '';
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>