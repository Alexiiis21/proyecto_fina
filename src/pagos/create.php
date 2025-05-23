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

// Obtener tarjetas de circulación
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
    header("Location: index.php");
    exit;
}

// Variables para el formulario
$pago = [
    'NumeroTransaccion' => '',
    'LineaCaptura' => '',
    'FechaLimite' => '',
    'FechaPago' => date('Y-m-d'), 
    'Importe' => '',
    'ID_Tarjeta_Circulacion' => '',
    'MetodoPago' => ''
];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pago['NumeroTransaccion'] = isset($_POST['numeroTransaccion']) ? trim($_POST['numeroTransaccion']) : '';
    $pago['LineaCaptura'] = isset($_POST['lineaCaptura']) ? trim($_POST['lineaCaptura']) : '';
    $pago['FechaLimite'] = isset($_POST['fechaLimite']) ? trim($_POST['fechaLimite']) : '';
    $pago['FechaPago'] = isset($_POST['fechaPago']) ? trim($_POST['fechaPago']) : '';
    $pago['Importe'] = isset($_POST['importe']) ? trim($_POST['importe']) : '';
    $pago['ID_Tarjeta_Circulacion'] = isset($_POST['tarjetaCirculacion']) ? trim($_POST['tarjetaCirculacion']) : '';
    $pago['MetodoPago'] = isset($_POST['metodoPago']) ? trim($_POST['metodoPago']) : '';

    if (empty($pago['NumeroTransaccion'])) {
        $errores[] = "El número de transacción es obligatorio.";
    } elseif (strlen($pago['NumeroTransaccion']) > 50) {
        $errores[] = "El número de transacción no debe exceder 50 caracteres.";
    }

    if (strlen($pago['LineaCaptura']) > 50) {
        $errores[] = "La línea de captura no debe exceder 50 caracteres.";
    }

    if (empty($pago['FechaLimite'])) {
        $errores[] = "La fecha límite de pago es obligatoria.";
    }

    if (empty($pago['FechaPago'])) {
        $errores[] = "La fecha de pago es obligatoria.";
    } else if ($pago['FechaPago'] > date('Y-m-d')) {
        $errores[] = "La fecha de pago no puede ser mayor a la fecha actual.";
    }

    if (!empty($pago['FechaLimite']) && !empty($pago['FechaPago']) && $pago['FechaPago'] > $pago['FechaLimite']) {
        $_SESSION['warning'] = "El pago se realizó después de la fecha límite.";
    }

    if (empty($pago['Importe'])) {
        $errores[] = "El importe es obligatorio.";
    } elseif (!is_numeric($pago['Importe']) || $pago['Importe'] <= 0) {
        $errores[] = "El importe debe ser un número positivo.";
    }

    if (empty($pago['ID_Tarjeta_Circulacion'])) {
        $errores[] = "Debe seleccionar una tarjeta de circulación.";
    }

    if (empty($pago['MetodoPago'])) {
        $errores[] = "El método de pago es obligatorio.";
    }

    if (!empty($pago['NumeroTransaccion'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE NumeroTransaccion = ?");
            $stmt->execute([$pago['NumeroTransaccion']]);
            if ($stmt->fetchColumn() > 0) {
                $errores[] = "El número de transacción ya existe. No se pueden duplicar transacciones.";
            }
        } catch (PDOException $e) {
            $errores[] = "Error al verificar el número de transacción: " . $e->getMessage();
        }
    }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pagos (NumeroTransaccion, LineaCaptura, FechaLimite, FechaPago, Importe, ID_Tarjeta_Circulacion, MetodoPago)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $pago['NumeroTransaccion'],
                $pago['LineaCaptura'],
                $pago['FechaLimite'],
                $pago['FechaPago'],
                $pago['Importe'],
                $pago['ID_Tarjeta_Circulacion'],
                $pago['MetodoPago']
            ]);
            
            if ($result) {
                $_SESSION['success'] = "Pago registrado correctamente.";
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error'] = "Error al registrar el pago.";
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
        <h1>Registrar Nuevo Pago</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
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

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['warning']; 
                unset($_SESSION['warning']);
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
                        <h5 class="card-title border-bottom pb-2">Datos del Pago</h5>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="numeroTransaccion" class="form-label">Número de Transacción *</label>
                        <input type="text" class="form-control" id="numeroTransaccion" name="numeroTransaccion" value="<?php echo htmlspecialchars($pago['NumeroTransaccion']); ?>" required>
                        <div class="form-text">Identificador único de la transacción</div>
                    </div>
                    <div class="col-md-6">
                        <label for="lineaCaptura" class="form-label">Línea de Captura</label>
                        <input type="text" class="form-control" id="lineaCaptura" name="lineaCaptura" value="<?php echo htmlspecialchars($pago['LineaCaptura']); ?>">
                        <div class="form-text">Si aplica</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="fechaLimite" class="form-label">Fecha Límite de Pago *</label>
                        <input type="date" class="form-control" id="fechaLimite" name="fechaLimite" value="<?php echo htmlspecialchars($pago['FechaLimite']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="fechaPago" class="form-label">Fecha de Pago *</label>
                        <input type="date" class="form-control" id="fechaPago" name="fechaPago" value="<?php echo htmlspecialchars($pago['FechaPago']); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="importe" class="form-label">Importe ($) *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="importe" name="importe" value="<?php echo htmlspecialchars($pago['Importe']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="metodoPago" class="form-label">Método de Pago *</label>
                        <select class="form-select" id="metodoPago" name="metodoPago" required>
                            <option value="">Seleccione un método de pago</option>
                            <option value="EFECTIVO" <?php if($pago['MetodoPago'] === 'EFECTIVO') echo 'selected'; ?>>Efectivo</option>
                            <option value="TARJETA" <?php if($pago['MetodoPago'] === 'TARJETA') echo 'selected'; ?>>Tarjeta de Crédito/Débito</option>
                            <option value="TRANSFERENCIA" <?php if($pago['MetodoPago'] === 'TRANSFERENCIA') echo 'selected'; ?>>Transferencia Bancaria</option>
                            <option value="CHEQUE" <?php if($pago['MetodoPago'] === 'CHEQUE') echo 'selected'; ?>>Cheque</option>
                            <option value="OTRO" <?php if($pago['MetodoPago'] === 'OTRO') echo 'selected'; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="tarjetaCirculacion" class="form-label">Tarjeta de Circulación *</label>
                        <select class="form-select" id="tarjetaCirculacion" name="tarjetaCirculacion" required>
                            <option value="">Seleccione una tarjeta de circulación</option>
                            <?php foreach ($tarjetas as $tarjeta): ?>
                                <option value="<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" <?php if($pago['ID_Tarjeta_Circulacion'] == $tarjeta['ID_Tarjeta_Circulacion']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($tarjeta['Placas'] . ' - ' . $tarjeta['Marca'] . ' ' . $tarjeta['Modelo'] . ' (' . $tarjeta['AnoFabricacion'] . ') - ' . $tarjeta['PropietarioNombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-end">
                        <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Registrar Pago</button>
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
    
    // Verificar fechas
    const fechaLimite = document.getElementById('fechaLimite');
    const fechaPago = document.getElementById('fechaPago');
    
    fechaPago.addEventListener('change', function() {
        if (fechaLimite.value && fechaPago.value > fechaLimite.value) {
            if (!document.getElementById('fecha-warning')) {
                const warning = document.createElement('div');
                warning.id = 'fecha-warning';
                warning.className = 'alert alert-warning mt-2';
                warning.textContent = 'Advertencia: La fecha de pago es posterior a la fecha límite.';
                fechaPago.parentNode.appendChild(warning);
            }
        } else {
            const warning = document.getElementById('fecha-warning');
            if (warning) {
                warning.remove();
            }
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>