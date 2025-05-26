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
    $stmt = $pdo->prepare("SELECT * FROM multas WHERE ID_Multa = ?");
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
    $stmt = $pdo->query("
        SELECT l.ID_Licencia, l.NumeroLicencia, c.Nombre AS NombreConductor
        FROM licencias l
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        ORDER BY l.NumeroLicencia
    ");
    $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar licencias: " . $e->getMessage();
    $licencias = [];
}

try {
    $stmt = $pdo->query("
        SELECT ID_Oficial, Nombre, NumeroIdentificacion 
        FROM oficiales 
        ORDER BY Nombre
    ");
    $oficiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar oficiales: " . $e->getMessage();
    $oficiales = [];
}

try {
    $stmt = $pdo->query("
        SELECT tc.ID_Tarjeta_Circulacion, tc.Placas, v.Marca, v.Modelo, v.AnoFabricacion, p.Nombre as PropietarioNombre
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios p ON tc.ID_Propietario = p.ID_Propietario
        ORDER BY tc.Placas ASC
    ");
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar las tarjetas de circulación: " . $e->getMessage();
    $tarjetas = [];
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $multa['Fecha'] = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $multa['Motivo'] = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
    $multa['Importe'] = isset($_POST['importe']) ? trim($_POST['importe']) : '';
    $multa['ID_Licencia'] = isset($_POST['id_licencia']) ? trim($_POST['id_licencia']) : '';
    $multa['ID_Oficial'] = isset($_POST['id_oficial']) ? trim($_POST['id_oficial']) : '';
    $multa['ID_Tarjeta_Circulacion'] = isset($_POST['id_tarjeta']) ? trim($_POST['id_tarjeta']) : '';

    if (empty($multa['Fecha'])) {
        $errores[] = "La fecha de la multa es obligatoria.";
    } else if ($multa['Fecha'] > date('Y-m-d')) {
        $errores[] = "La fecha de la multa no puede ser posterior a la fecha actual.";
    }

    if (empty($multa['Motivo'])) {
        $errores[] = "El motivo de la multa es obligatorio.";
    } elseif (strlen($multa['Motivo']) > 255) {
        $errores[] = "El motivo no debe exceder 255 caracteres.";
    }

    if (empty($multa['Importe'])) {
        $errores[] = "El importe es obligatorio.";
    } elseif (!is_numeric($multa['Importe']) || $multa['Importe'] <= 0) {
        $errores[] = "El importe debe ser un número positivo.";
    }

    if (empty($multa['ID_Licencia'])) {
        $errores[] = "Debe seleccionar una licencia.";
    }

    if (empty($multa['ID_Oficial'])) {
        $errores[] = "Debe seleccionar un oficial.";
    }

    if (empty($multa['ID_Tarjeta_Circulacion'])) {
        $errores[] = "Debe seleccionar una tarjeta de circulación.";
    }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE multas 
                SET Fecha = ?, 
                    Motivo = ?, 
                    Importe = ?, 
                    ID_Licencia = ?, 
                    ID_Oficial = ?, 
                    ID_Tarjeta_Circulacion = ?
                WHERE ID_Multa = ?
            ");
            
            $result = $stmt->execute([
                $multa['Fecha'],
                $multa['Motivo'],
                $multa['Importe'],
                $multa['ID_Licencia'],
                $multa['ID_Oficial'],
                $multa['ID_Tarjeta_Circulacion'],
                $id
            ]);
            
            if ($result) {
                $_SESSION['success'] = "Multa actualizada correctamente.";
                header("Location: view.php?id=$id");
                exit;
            } else {
                $_SESSION['error'] = "Error al actualizar la multa.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar Multa</h1>
        <div>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-info me-2">
                <i class="bi bi-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Listado
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
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="card-title border-bottom pb-2">Datos de la Multa</h5>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="fecha" class="form-label">Fecha de la Multa *</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars($multa['Fecha']); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        <div class="invalid-feedback">La fecha es obligatoria y no puede ser posterior a hoy.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="importe" class="form-label">Importe ($) *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="importe" name="importe" value="<?php echo htmlspecialchars($multa['Importe']); ?>" required>
                            <div class="invalid-feedback">El importe debe ser mayor a cero.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="id_oficial" class="form-label">Oficial *</label>
                        <div class="input-group">
                            <select class="form-select" id="id_oficial" name="id_oficial" required>
                                <option value="">Seleccione un oficial...</option>
                                <?php foreach ($oficiales as $oficial): ?>
                                    <option value="<?php echo $oficial['ID_Oficial']; ?>" <?php if($multa['ID_Oficial'] == $oficial['ID_Oficial']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($oficial['Nombre']); ?> 
                                        (ID: <?php echo htmlspecialchars($oficial['NumeroIdentificacion']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="openOficialPopup()">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <div class="invalid-feedback">Debe seleccionar un oficial.</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="id_licencia" class="form-label">Licencia *</label>
                        <select class="form-select" id="id_licencia" name="id_licencia" required>
                            <option value="">Seleccione una licencia...</option>
                            <?php foreach ($licencias as $licencia): ?>
                                <option value="<?php echo $licencia['ID_Licencia']; ?>" <?php if($multa['ID_Licencia'] == $licencia['ID_Licencia']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($licencia['NumeroLicencia']); ?> - 
                                    <?php echo htmlspecialchars($licencia['NombreConductor'] ?? 'Sin conductor asignado'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar una licencia.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="id_tarjeta" class="form-label">Tarjeta de Circulación *</label>
                        <select class="form-select" id="id_tarjeta" name="id_tarjeta" required>
                            <option value="">Seleccione una tarjeta de circulación...</option>
                            <?php foreach ($tarjetas as $tarjeta): ?>
                                <option value="<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" <?php if($multa['ID_Tarjeta_Circulacion'] == $tarjeta['ID_Tarjeta_Circulacion']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($tarjeta['Placas']); ?> - 
                                    <?php echo htmlspecialchars($tarjeta['Marca'] . ' ' . $tarjeta['Modelo'] . ' (' . $tarjeta['AnoFabricacion'] . ') - ' . ($tarjeta['PropietarioNombre'] ?? 'Sin propietario')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar una tarjeta de circulación.</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <label for="motivo" class="form-label">Motivo de la Multa *</label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" maxlength="255" required><?php echo htmlspecialchars($multa['Motivo']); ?></textarea>
                        <div class="form-text">Describa detalladamente el motivo de la infracción. Máximo 255 caracteres.</div>
                        <div class="invalid-feedback">El motivo de la multa es obligatorio.</div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
    
    // Contador de caracteres para el motivo
    const motivoTextarea = document.getElementById('motivo');
    const maxLength = motivoTextarea.getAttribute('maxlength');
    
    function updateCounter() {
        const currentLength = motivoTextarea.value.length;
        const counter = document.getElementById('motivo-counter');
        
        if (!counter) {
            const newCounter = document.createElement('small');
            newCounter.id = 'motivo-counter';
            newCounter.className = 'text-muted';
            newCounter.textContent = `${currentLength}/${maxLength} caracteres`;
            motivoTextarea.parentNode.appendChild(newCounter);
        } else {
            counter.textContent = `${currentLength}/${maxLength} caracteres`;
            if (currentLength >= maxLength - 20) {
                counter.className = 'text-danger';
            } else {
                counter.className = 'text-muted';
            }
        }
    }
    
    motivoTextarea.addEventListener('input', updateCounter);
    updateCounter(); 
});

function openOficialPopup() {
    const popupWindow = window.open('../oficiales/create.php?popup=1', 'nuevoOficial', 'width=800,height=600,scrollbars=yes');
    
    if (popupWindow) {
        popupWindow.focus();
    } else {
        alert('La ventana emergente fue bloqueada por el navegador. Por favor, permita ventanas emergentes para este sitio.');
    }
}

function addOficial(oficial) {
    const select = document.getElementById('id_oficial');
    
    const option = document.createElement('option');
    option.value = oficial.id;
    option.text = oficial.nombre + ' (ID: ' + oficial.numeroIdentificacion + ')';
    option.selected = true;
    
    select.appendChild(option);
    
    const helpText = document.getElementById('oficial-help');
    const form = document.querySelector('form');
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show mt-2';
    alertDiv.innerHTML = `
        <i class="bi bi-check-circle-fill"></i> Oficial agregado correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    form.insertBefore(alertDiv, form.firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

window.addOficial = addOficial;
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>