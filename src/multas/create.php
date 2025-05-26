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

try {
    $stmt = $pdo->prepare("
        SELECT l.ID_Licencia, l.NumeroLicencia, c.Nombre AS NombreConductor
        FROM licencias l
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        ORDER BY l.NumeroLicencia
    ");
    $stmt->execute();
    $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar licencias: " . $e->getMessage();
    $licencias = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT ID_Oficial, Nombre, NumeroIdentificacion
        FROM oficiales
        ORDER BY Nombre
    ");
    $stmt->execute();
    $oficiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar oficiales: " . $e->getMessage();
    $oficiales = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT tc.ID_Tarjeta_Circulacion, tc.Placas, 
               v.Marca, v.Modelo, v.AnoFabricacion
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        ORDER BY tc.Placas
    ");
    $stmt->execute();
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar tarjetas de circulación: " . $e->getMessage();
    $tarjetas = [];
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['fecha'])) {
        $errores[] = "La fecha es obligatoria";
    } else {
        $fecha = $_POST['fecha'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $errores[] = "El formato de fecha debe ser YYYY-MM-DD";
        }
    }
    
    if (empty($_POST['motivo'])) {
        $errores[] = "El motivo de la multa es obligatorio";
    } else {
        $motivo = trim($_POST['motivo']);
        if (strlen($motivo) > 255) {
            $errores[] = "El motivo no puede exceder los 255 caracteres";
        }
    }
    
    if (empty($_POST['importe'])) {
        $errores[] = "El importe de la multa es obligatorio";
    } else {
        $importe = (float) str_replace(',', '.', $_POST['importe']);
        if ($importe <= 0) {
            $errores[] = "El importe debe ser mayor que cero";
        }
    }
    
    if (empty($_POST['id_licencia'])) {
        $errores[] = "Debe seleccionar una licencia";
    } else {
        $idLicencia = (int) $_POST['id_licencia'];
    }
    
    if (empty($_POST['id_oficial'])) {
        $errores[] = "Debe seleccionar un oficial";
    } else {
        $idOficial = (int) $_POST['id_oficial'];
    }
    
    if (empty($_POST['id_tarjeta'])) {
        $errores[] = "Debe seleccionar una tarjeta de circulación";
    } else {
        $idTarjeta = (int) $_POST['id_tarjeta'];
    }
    
    // Si no hay errores, guarda la multa
    if (empty($errores)) {
        try {
            $query = "
                INSERT INTO multas (
                    Fecha, Motivo, Importe, ID_Licencia, ID_Oficial, ID_Tarjeta_Circulacion
                ) VALUES (
                    :fecha, :motivo, :importe, :id_licencia, :id_oficial, :id_tarjeta
                )
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':fecha', $fecha);
            $stmt->bindParam(':motivo', $motivo);
            $stmt->bindParam(':importe', $importe);
            $stmt->bindParam(':id_licencia', $idLicencia);
            $stmt->bindParam(':id_oficial', $idOficial);
            $stmt->bindParam(':id_tarjeta', $idTarjeta);
            
            $resultado = $stmt->execute();
            
            if ($resultado) {
                $idMulta = $pdo->lastInsertId();
                $_SESSION['success'] = "Multa registrada correctamente.";
                header("Location: view.php?id=" . $idMulta);
                exit;
            } else {
                $errores[] = "Error al guardar la multa en la base de datos";
            }
        } catch (PDOException $e) {
            $errores[] = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Registrar Nueva Multa</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    
    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="mb-3">Datos de la Infracción</h3>
                        
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha de la Multa <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha" name="fecha" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="motivo" name="motivo" rows="3" required><?php echo isset($motivo) ? htmlspecialchars($motivo) : ''; ?></textarea>
                            <div class="form-text">Describa el motivo de la infracción (máximo 255 caracteres)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="importe" class="form-label">Importe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="importe" name="importe" step="0.01" min="0.01" required value="<?php echo isset($importe) ? htmlspecialchars($importe) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3 class="mb-3">Información Relacionada</h3>
                        
                        <div class="mb-3">
                            <label for="id_licencia" class="form-label">Licencia <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_licencia" name="id_licencia" required>
                                <option value="">Seleccione una licencia...</option>
                                <?php foreach ($licencias as $licencia): ?>
                                    <option value="<?php echo $licencia['ID_Licencia']; ?>" <?php echo (isset($idLicencia) && $idLicencia == $licencia['ID_Licencia']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($licencia['NumeroLicencia']); ?> - 
                                        <?php echo htmlspecialchars($licencia['NombreConductor'] ?? 'Sin conductor asignado'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
    <label for="id_oficial" class="form-label">Oficial <span class="text-danger">*</span></label>
    <div class="input-group">
        <select class="form-select" id="id_oficial" name="id_oficial" required>
            <option value="">Seleccione un oficial...</option>
            <?php foreach ($oficiales as $oficial): ?>
                <option value="<?php echo $oficial['ID_Oficial']; ?>" <?php echo (isset($idOficial) && $idOficial == $oficial['ID_Oficial']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($oficial['Nombre']); ?> 
                    (Placa: <?php echo htmlspecialchars($oficial['NumeroPlaca']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="openOficialPopup()">
            <i class="bi bi-plus-circle"></i> Nuevo
        </button>
    </div>
    <div class="form-text" id="oficial-help">
        Seleccione un oficial existente o registre uno nuevo con el botón.
    </div>
</div>
                        
                        <div class="mb-3">
                            <label for="id_tarjeta" class="form-label">Tarjeta de Circulación <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_tarjeta" name="id_tarjeta" required>
                                <option value="">Seleccione una tarjeta de circulación...</option>
                                <?php foreach ($tarjetas as $tarjeta): ?>
                                    <option value="<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" <?php echo (isset($idTarjeta) && $idTarjeta == $tarjeta['ID_Tarjeta_Circulacion']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tarjeta['Placas']); ?> - 
                                        <?php 
                                            $infoVehiculo = '';
                                            if (!empty($tarjeta['Marca']) && !empty($tarjeta['Modelo'])) {
                                                $infoVehiculo = $tarjeta['Marca'] . ' ' . $tarjeta['Modelo'];
                                                if (!empty($tarjeta['AnoFabricacion'])) {
                                                    $infoVehiculo .= ' (' . $tarjeta['AnoFabricacion'] . ')';
                                                }
                                            } else {
                                                $infoVehiculo = 'Sin vehículo asignado';
                                            }
                                            echo htmlspecialchars($infoVehiculo); 
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Multa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para la gestión de oficiales en ventana emergente
function openOficialPopup() {
    // Abrir ventana emergente
    const popupWindow = window.open('../oficiales/create.php?popup=1', 'nuevoOficial', 'width=800,height=600,scrollbars=yes');
    
    // Verificar si la ventana se abrió correctamente
    if (popupWindow) {
        popupWindow.focus();
    } else {
        alert('La ventana emergente fue bloqueada por el navegador. Por favor, permita ventanas emergentes para este sitio.');
    }
}

// Función para recibir el nuevo oficial desde la ventana emergente
function addOficial(oficial) {
    const select = document.getElementById('id_oficial');
    
    // Crear una nueva opción para el select
    const option = document.createElement('option');
    option.value = oficial.id;
    option.text = oficial.nombre + ' (Placa: ' + oficial.numeroPlaca + ')';
    option.selected = true;
    
    // Agregar la opción al select
    select.appendChild(option);
    
    // Mostrar mensaje de éxito temporal
    const helpText = document.getElementById('oficial-help');
    const originalText = helpText.innerHTML;
    
    helpText.innerHTML = '<span class="text-success">¡Oficial agregado correctamente!</span>';
    helpText.classList.add('text-success');
    
    setTimeout(() => {
        helpText.innerHTML = originalText;
        helpText.classList.remove('text-success');
    }, 3000);
}

// Para que la función sea accesible globalmente
window.addOficial = addOficial;
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>