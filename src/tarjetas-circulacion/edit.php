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
    $_SESSION['error'] = "ID de tarjeta no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];
$errors = [];
$tarjeta = null;

// Obtener datos de la tarjeta actual
try {
    $stmt = $pdo->prepare("
        SELECT * FROM tarjetascirculacion 
        WHERE ID_Tarjeta_Circulacion = ?
    ");
    $stmt->execute([$id]);
    $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarjeta) {
        $_SESSION['error'] = "La tarjeta de circulación no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la tarjeta: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Obtener lista de vehículos disponibles
try {
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

    // Si no hay errores, actualizar en la base de datos
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tarjetascirculacion SET
                    ID_Propietario = ?,
                    ID_Vehiculo = ?,
                    Placas = ?,
                    Municipio = ?,
                    Estado = ?,
                    Localidad = ?,
                    TipoServicio = ?,
                    FechaExpedicion = ?,
                    FechaVencimiento = ?,
                    Origen = ?
                WHERE ID_Tarjeta_Circulacion = ?
            ");
            
            $result = $stmt->execute([
                $idPropietario,
                $idVehiculo,
                $placas,
                $municipio,
                $estado,
                $localidad,
                $tipoServicio,
                $fechaExpedicion,
                $fechaVencimiento,
                $origen,
                $id
            ]);
            
            if ($result) {
                $_SESSION['success'] = "Tarjeta de circulación actualizada correctamente.";
                header("Location: view.php?id=" . $id);
                exit;
            } else {
                $errors[] = "Error al actualizar la tarjeta de circulación";
            }
        } catch (PDOException $e) {
            $errors[] = "Error de base de datos: " . $e->getMessage();
        }
    }
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Tarjeta de Circulación</h1>
        <div>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-info me-2">
                <i class="bi bi-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
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
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="placas" class="form-label">Placas *</label>
                        <input type="text" class="form-control" id="placas" name="placas" 
                               value="<?php echo htmlspecialchars($tarjeta['Placas']); ?>" required>
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
                                        <?php echo ($tarjeta['ID_Vehiculo'] == $vehiculo['ID_Vehiculo']) ? 'selected' : ''; ?>>
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
                                        <?php echo ($tarjeta['ID_Propietario'] == $propietario['ID_Propietario']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($propietario['Nombre'] . ' - ' . $propietario['RFC']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="tipoServicio" class="form-label">Tipo de Servicio *</label>
                        <select class="form-select" id="tipoServicio" name="tipoServicio" required>
                            <option value="">Seleccione tipo de servicio</option>
                            <option value="PERSONAL" <?php echo ($tarjeta['TipoServicio'] == 'PERSONAL') ? 'selected' : ''; ?>>PERSONAL</option>
                            <option value="PÚBLICO" <?php echo ($tarjeta['TipoServicio'] == 'PÚBLICO') ? 'selected' : ''; ?>>PÚBLICO</option>
                            <option value="CARGA" <?php echo ($tarjeta['TipoServicio'] == 'CARGA') ? 'selected' : ''; ?>>CARGA</option>
                            <option value="FEDERAL" <?php echo ($tarjeta['TipoServicio'] == 'FEDERAL') ? 'selected' : ''; ?>>FEDERAL</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="municipio" class="form-label">Municipio *</label>
                        <input type="text" class="form-control" id="municipio" name="municipio" 
                               value="<?php echo htmlspecialchars($tarjeta['Municipio']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="estado" class="form-label">Estado *</label>
                        <input type="text" class="form-control" id="estado" name="estado" 
                               value="<?php echo htmlspecialchars($tarjeta['Estado']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="localidad" class="form-label">Localidad *</label>
                        <input type="text" class="form-control" id="localidad" name="localidad" 
                               value="<?php echo htmlspecialchars($tarjeta['Localidad']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="fechaExpedicion" class="form-label">Fecha de Expedición *</label>
                        <input type="date" class="form-control" id="fechaExpedicion" name="fechaExpedicion" 
                               value="<?php echo htmlspecialchars($tarjeta['FechaExpedicion']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="fechaVencimiento" class="form-label">Fecha de Vencimiento *</label>
                        <input type="date" class="form-control" id="fechaVencimiento" name="fechaVencimiento" 
                               value="<?php echo htmlspecialchars($tarjeta['FechaVencimiento']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="origen" class="form-label">Origen *</label>
                        <select class="form-select" id="origen" name="origen" required>
                            <option value="">Seleccione origen</option>
                            <option value="MEXICANO" <?php echo ($tarjeta['Origen'] == 'MEXICANO') ? 'selected' : ''; ?>>MEXICANO</option>
                            <option value="EXTRANJERO" <?php echo ($tarjeta['Origen'] == 'EXTRANJERO') ? 'selected' : ''; ?>>EXTRANJERO</option>
                            <option value="FRONTERIZO" <?php echo ($tarjeta['Origen'] == 'FRONTERIZO') ? 'selected' : ''; ?>>FRONTERIZO</option>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
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