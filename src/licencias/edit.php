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
$errors = [];
$success = false;

// Obtener lista de conductores y domicilios para el formulario
$conductores = getAllConductores($pdo);
$domicilios = getAllDomicilios($pdo);

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y limpiar los datos recibidos
    $numeroLicencia = cleanInput($_POST['numeroLicencia'] ?? '');
    $fechaNacimiento = cleanInput($_POST['fechaNacimiento'] ?? '');
    $fechaExpedicion = cleanInput($_POST['fechaExpedicion'] ?? '');
    $vigencia = cleanInput($_POST['vigencia'] ?? '');
    $antiguedad = (int)cleanInput($_POST['antiguedad'] ?? 0);
    $tipoLicencia = cleanInput($_POST['tipoLicencia'] ?? '');
    $grupoSanguineo = cleanInput($_POST['grupoSanguineo'] ?? '');
    $idConductor = (int)cleanInput($_POST['idConductor'] ?? 0);
    $idDomicilio = (int)cleanInput($_POST['idDomicilio'] ?? 0);

    // Validaciones
    if (empty($numeroLicencia)) {
        $errors[] = "El número de licencia es obligatorio";
    }

    if (!validateDate($fechaNacimiento)) {
        $errors[] = "La fecha de nacimiento no es válida";
    }

    if (!validateDate($fechaExpedicion)) {
        $errors[] = "La fecha de expedición no es válida";
    }

    if (!validateDate($vigencia)) {
        $errors[] = "La fecha de vigencia no es válida";
    }

    if ($antiguedad <= 0) {
        $errors[] = "La antigüedad debe ser mayor a 0";
    }

    if (empty($tipoLicencia)) {
        $errors[] = "El tipo de licencia es obligatorio";
    }

    if (empty($grupoSanguineo)) {
        $errors[] = "El grupo sanguíneo es obligatorio";
    }

    if ($idConductor <= 0) {
        $errors[] = "Debe seleccionar un conductor";
    }

    if ($idDomicilio <= 0) {
        $errors[] = "Debe seleccionar un domicilio";
    }

    // Si no hay errores, actualizar en la base de datos
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE licencias 
                SET NumeroLicencia = ?, FechaNacimiento = ?, FechaExpedicion = ?,
                    Vigencia = ?, Antiguedad = ?, TipoLicencia = ?,
                    GrupoSanguineo = ?, ID_Conductor = ?, ID_Domicilio = ?
                WHERE ID_Licencia = ?
            ");
            
            $result = $stmt->execute([
                $numeroLicencia, $fechaNacimiento, $fechaExpedicion,
                $vigencia, $antiguedad, $tipoLicencia,
                $grupoSanguineo, $idConductor, $idDomicilio,
                $id
            ]);
            
            if ($result) {
                $success = true;
                $_SESSION['success'] = "Licencia actualizada correctamente.";
            } else {
                $errors[] = "Error al actualizar la licencia";
            }
        } catch (PDOException $e) {
            $errors[] = "Error de base de datos: " . $e->getMessage();
        }
    }
} else {
    // Es una petición GET, cargar los datos de la licencia
    try {
        $stmt = $pdo->prepare("SELECT * FROM licencias WHERE ID_Licencia = ?");
        $stmt->execute([$id]);
        $licencia = $stmt->fetch();
        
        if (!$licencia) {
            $_SESSION['error'] = "La licencia solicitada no existe.";
            header("Location: index.php");
            exit;
        }
        
        // Preparar los datos para el formulario
        $numeroLicencia = $licencia['NumeroLicencia'];
        $fechaNacimiento = $licencia['FechaNacimiento'];
        $fechaExpedicion = $licencia['FechaExpedicion'];
        $vigencia = $licencia['Vigencia'];
        $antiguedad = $licencia['Antiguedad'];
        $tipoLicencia = $licencia['TipoLicencia'];
        $grupoSanguineo = $licencia['GrupoSanguineo'];
        $idConductor = $licencia['ID_Conductor'];
        $idDomicilio = $licencia['ID_Domicilio'];
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al cargar los datos de la licencia: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Licencia</h1>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            Licencia actualizada exitosamente. 
            <a href="view.php?id=<?php echo $id; ?>">Ver detalles</a> o 
            <a href="index.php">volver a la lista</a>.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $id); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="numeroLicencia" class="form-label">Número de Licencia *</label>
                        <input type="text" class="form-control" id="numeroLicencia" name="numeroLicencia" 
                               value="<?php echo htmlspecialchars($numeroLicencia); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="idConductor" class="form-label">Conductor *</label>
                        <select class="form-select" id="idConductor" name="idConductor" required>
                            <option value="">Seleccione un conductor</option>
                            <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['ID_Conductor']; ?>" 
                                        <?php echo ($idConductor == $conductor['ID_Conductor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conductor['Nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="fechaNacimiento" class="form-label">Fecha de Nacimiento *</label>
                        <input type="date" class="form-control" id="fechaNacimiento" name="fechaNacimiento"
                               value="<?php echo htmlspecialchars($fechaNacimiento); ?>" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="fechaExpedicion" class="form-label">Fecha de Expedición *</label>
                        <input type="date" class="form-control" id="fechaExpedicion" name="fechaExpedicion"
                               value="<?php echo htmlspecialchars($fechaExpedicion); ?>" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="vigencia" class="form-label">Fecha de Vigencia *</label>
                        <input type="date" class="form-control" id="vigencia" name="vigencia"
                               value="<?php echo htmlspecialchars($vigencia); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="antiguedad" class="form-label">Antigüedad (años) *</label>
                        <input type="number" class="form-control" id="antiguedad" name="antiguedad" min="1"
                               value="<?php echo htmlspecialchars($antiguedad); ?>" required>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="tipoLicencia" class="form-label">Tipo de Licencia *</label>
                        <select class="form-select" id="tipoLicencia" name="tipoLicencia" required>
                            <option value="">Seleccione un tipo</option>
                            <option value="A" <?php echo ($tipoLicencia == 'A') ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?php echo ($tipoLicencia == 'B') ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?php echo ($tipoLicencia == 'C') ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?php echo ($tipoLicencia == 'D') ? 'selected' : ''; ?>>D</option>
                            <option value="E" <?php echo ($tipoLicencia == 'E') ? 'selected' : ''; ?>>E</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="grupoSanguineo" class="form-label">Grupo Sanguíneo *</label>
                        <select class="form-select" id="grupoSanguineo" name="grupoSanguineo" required>
                            <option value="">Seleccione grupo sanguíneo</option>
                            <option value="O+" <?php echo ($grupoSanguineo == 'O+') ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo ($grupoSanguineo == 'O-') ? 'selected' : ''; ?>>O-</option>
                            <option value="A+" <?php echo ($grupoSanguineo == 'A+') ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo ($grupoSanguineo == 'A-') ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo ($grupoSanguineo == 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo ($grupoSanguineo == 'B-') ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo ($grupoSanguineo == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo ($grupoSanguineo == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="idDomicilio" class="form-label">Domicilio *</label>
                    <select class="form-select" id="idDomicilio" name="idDomicilio" required>
                        <option value="">Seleccione un domicilio</option>
                        <?php foreach ($domicilios as $domicilio): ?>
                            <option value="<?php echo $domicilio['ID_Domicilio']; ?>"
                                    <?php echo ($idDomicilio == $domicilio['ID_Domicilio']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($domicilio['Direccion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>