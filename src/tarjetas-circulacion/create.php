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

// Variables para el formulario
$errors = [];
$success = false;

// Obtener lista de vehículos disponibles
try {
    // Corregido: obtenemos solo los datos de vehículos sin intentar unir con la tabla propietarios
    $stmt = $pdo->query("
        SELECT * FROM vehiculos
        ORDER BY Marca, Modelo
    ");
    $vehiculos = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener la lista de vehículos: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Obtener lista de propietarios
try {
    $stmt = $pdo->query("
        SELECT * FROM propietarios 
        ORDER BY Nombre
    ");
    $propietarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener la lista de propietarios: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y limpiar datos
    $placas = cleanInput($_POST['placas'] ?? '');
    $idVehiculo = (int)cleanInput($_POST['idVehiculo'] ?? 0);
    $idPropietario = (int)cleanInput($_POST['idPropietario'] ?? 0);
    $municipio = cleanInput($_POST['municipio'] ?? '');
    $estado = cleanInput($_POST['estado'] ?? '');
    $localidad = cleanInput($_POST['localidad'] ?? '');
    $tipoServicio = cleanInput($_POST['tipoServicio'] ?? '');
    $fechaExpedicion = cleanInput($_POST['fechaExpedicion'] ?? '');
    $fechaVencimiento = cleanInput($_POST['fechaVencimiento'] ?? '');
    $origen = cleanInput($_POST['origen'] ?? '');

    // Validaciones
    if (empty($placas)) {
        $errors[] = "Las placas son obligatorias";
    }

    if ($idVehiculo <= 0) {
        $errors[] = "Debe seleccionar un vehículo";
    }

    if ($idPropietario <= 0) {
        $errors[] = "Debe seleccionar un propietario";
    }

    if (empty($municipio)) {
        $errors[] = "El municipio es obligatorio";
    }

    if (empty($estado)) {
        $errors[] = "El estado es obligatorio";
    }

    if (empty($localidad)) {
        $errors[] = "La localidad es obligatoria";
    }

    if (empty($tipoServicio)) {
        $errors[] = "El tipo de servicio es obligatorio";
    }

    if (!validateDate($fechaExpedicion)) {
        $errors[] = "La fecha de expedición no es válida";
    }

    if (!validateDate($fechaVencimiento)) {
        $errors[] = "La fecha de vencimiento no es válida";
    }

    if (empty($origen)) {
        $errors[] = "El origen es obligatorio";
    }

    // Si no hay errores, guardar en la base de datos
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tarjetascirculacion (
                    ID_Propietario, ID_Vehiculo, Placas, Municipio, Estado, 
                    Localidad, TipoServicio, FechaExpedicion, FechaVencimiento, Origen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $idPropietario, $idVehiculo, $placas, $municipio, $estado,
                $localidad, $tipoServicio, $fechaExpedicion, $fechaVencimiento, $origen
            ]);
            
            if ($result) {
                $newId = $pdo->lastInsertId();
                $_SESSION['success'] = "Tarjeta de circulación registrada correctamente.";
                
                // Redireccionar a la página de visualización
                header("Location: view.php?id=" . $newId);
                exit;
            } else {
                $errors[] = "Error al registrar la tarjeta de circulación";
            }
        } catch (PDOException $e) {
            // Verificar si es un error de duplicidad
            if ($e->getCode() == 23000) {
                $errors[] = "Ya existe una tarjeta con estos datos.";
            } else {
                $errors[] = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Nueva Tarjeta de Circulación</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="placas" class="form-label">Placas *</label>
                        <input type="text" class="form-control" id="placas" name="placas" 
                               value="<?php echo htmlspecialchars($placas ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="idVehiculo" class="form-label">Vehículo *</label>
                        <select class="form-select" id="idVehiculo" name="idVehiculo" required>
                            <option value="">Seleccione un vehículo</option>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                                <?php 
                                    $detalleVehiculo = htmlspecialchars($vehiculo['Marca'] . ' ' . $vehiculo['Modelo'] . ' ' . $vehiculo['AnoFabricacion'] . ' - ' . $vehiculo['Placas']);
                                ?>
                                <option value="<?php echo $vehiculo['ID_Vehiculo']; ?>" 
                                        <?php echo (isset($idVehiculo) && $idVehiculo == $vehiculo['ID_Vehiculo']) ? 'selected' : ''; ?>>
                                    <?php echo $detalleVehiculo; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="idPropietario" class="form-label">Propietario *</label>
                        <select class="form-select" id="idPropietario" name="idPropietario" required>
                            <option value="">Seleccione un propietario</option>
                            <?php foreach ($propietarios as $propietario): ?>
                                <option value="<?php echo $propietario['ID_Propietario']; ?>" 
                                        <?php echo (isset($idPropietario) && $idPropietario == $propietario['ID_Propietario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($propietario['Nombre'] . ' - ' . $propietario['RFC']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tipoServicio" class="form-label">Tipo de Servicio *</label>
                        <select class="form-select" id="tipoServicio" name="tipoServicio" required>
                            <option value="">Seleccione tipo de servicio</option>
                            <option value="PERSONAL" <?php echo (isset($tipoServicio) && $tipoServicio == 'PERSONAL') ? 'selected' : ''; ?>>PERSONAL</option>
                            <option value="PÚBLICO" <?php echo (isset($tipoServicio) && $tipoServicio == 'PÚBLICO') ? 'selected' : ''; ?>>PÚBLICO</option>
                            <option value="CARGA" <?php echo (isset($tipoServicio) && $tipoServicio == 'CARGA') ? 'selected' : ''; ?>>CARGA</option>
                            <option value="FEDERAL" <?php echo (isset($tipoServicio) && $tipoServicio == 'FEDERAL') ? 'selected' : ''; ?>>FEDERAL</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="municipio" class="form-label">Municipio *</label>
                        <input type="text" class="form-control" id="municipio" name="municipio" 
                               value="<?php echo htmlspecialchars($municipio ?? ''); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="estado" class="form-label">Estado *</label>
                        <input type="text" class="form-control" id="estado" name="estado" 
                               value="<?php echo htmlspecialchars($estado ?? ''); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="localidad" class="form-label">Localidad *</label>
                        <input type="text" class="form-control" id="localidad" name="localidad" 
                               value="<?php echo htmlspecialchars($localidad ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="fechaExpedicion" class="form-label">Fecha de Expedición *</label>
                        <input type="date" class="form-control" id="fechaExpedicion" name="fechaExpedicion" 
                               value="<?php echo htmlspecialchars($fechaExpedicion ?? date('Y-m-d')); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="fechaVencimiento" class="form-label">Fecha de Vencimiento *</label>
                        <input type="date" class="form-control" id="fechaVencimiento" name="fechaVencimiento" 
                               value="<?php echo htmlspecialchars($fechaVencimiento ?? date('Y-m-d', strtotime('+1 year'))); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="origen" class="form-label">Origen *</label>
                        <select class="form-select" id="origen" name="origen" required>
                            <option value="">Seleccione origen</option>
                            <option value="MEXICANO" <?php echo (isset($origen) && $origen == 'MEXICANO') ? 'selected' : ''; ?>>MEXICANO</option>
                            <option value="EXTRANJERO" <?php echo (isset($origen) && $origen == 'EXTRANJERO') ? 'selected' : ''; ?>>EXTRANJERO</option>
                            <option value="FRONTERIZO" <?php echo (isset($origen) && $origen == 'FRONTERIZO') ? 'selected' : ''; ?>>FRONTERIZO</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Tarjeta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>