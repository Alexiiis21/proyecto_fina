<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación usando el sistema de API keys
redirect_if_not_authenticated('/auth/login.php');

// Variable para indicar si esta página fue llamada desde otro formulario
$esVentanaEmergente = isset($_GET['popup']) && $_GET['popup'] == 1;

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if ($esVentanaEmergente) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => ["ID de vehículo no válido."]
        ]);
        exit;
    } else {
        $_SESSION['error'] = "ID de vehículo no válido.";
        header("Location: index.php");
        exit;
    }
}

$id = (int) $_GET['id'];

// Obtener datos del vehículo
try {
    $stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE ID_Vehiculo = ?");
    $stmt->execute([$id]);
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vehiculo) {
        if ($esVentanaEmergente) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'errors' => ["Vehículo no encontrado."]
            ]);
            exit;
        } else {
            $_SESSION['error'] = "Vehículo no encontrado.";
            header("Location: index.php");
            exit;
        }
    }
} catch (PDOException $e) {
    if ($esVentanaEmergente) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => ["Error al obtener datos: " . $e->getMessage()]
        ]);
        exit;
    } else {
        $_SESSION['error'] = "Error al obtener datos: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificación adicional para operaciones sensibles
    if (!is_api_key_valid()) {
        $_SESSION['error'] = "Por seguridad, debes iniciar sesión nuevamente para realizar esta acción.";
        header("Location: ../auth/login.php");
        exit;
    }
    
    $errores = [];
    
    // Validar campos obligatorios
    if (empty($_POST['numero_serie'])) {
        $errores[] = "El número de serie es obligatorio.";
    }
    if (empty($_POST['placas'])) {
        $errores[] = "Las placas son obligatorias.";
    } elseif (strlen($_POST['placas']) > 10) {
        $errores[] = "Las placas no pueden tener más de 10 caracteres.";
    }
    if (empty($_POST['marca'])) {
        $errores[] = "La marca es obligatoria.";
    }
    if (empty($_POST['modelo'])) {
        $errores[] = "El modelo es obligatorio.";
    }
    if (empty($_POST['ano_fabricacion'])) {
        $errores[] = "El año de fabricación es obligatorio.";
    } elseif (!is_numeric($_POST['ano_fabricacion'])) {
        $errores[] = "El año de fabricación debe ser numérico.";
    } elseif ($_POST['ano_fabricacion'] < 1900 || $_POST['ano_fabricacion'] > date('Y') + 1) {
        $errores[] = "El año de fabricación no es válido.";
    }
    if (empty($_POST['color'])) {
        $errores[] = "El color es obligatorio.";
    }
    if (empty($_POST['numero_motor'])) {
        $errores[] = "El número de motor es obligatorio.";
    }
    if (empty($_POST['tipo_carroceria'])) {
        $errores[] = "El tipo de carrocería es obligatorio.";
    }
    if (empty($_POST['numero_asientos'])) {
        $errores[] = "El número de asientos es obligatorio.";
    } elseif (!is_numeric($_POST['numero_asientos'])) {
        $errores[] = "El número de asientos debe ser numérico.";
    }
    if (empty($_POST['cilindraje'])) {
        $errores[] = "El cilindraje es obligatorio.";
    } elseif (!is_numeric($_POST['cilindraje'])) {
        $errores[] = "El cilindraje debe ser numérico.";
    }
    if (empty($_POST['tipo_combustible'])) {
        $errores[] = "El tipo de combustible es obligatorio.";
    }
    if (empty($_POST['uso'])) {
        $errores[] = "El uso del vehículo es obligatorio.";
    }
    if (empty($_POST['transmision'])) {
        $errores[] = "El tipo de transmisión es obligatorio.";
    }
    if (empty($_POST['numero_puertas'])) {
        $errores[] = "El número de puertas es obligatorio.";
    } elseif (!is_numeric($_POST['numero_puertas'])) {
        $errores[] = "El número de puertas debe ser numérico.";
    }
    if (empty($_POST['clase'])) {
        $errores[] = "La clase del vehículo es obligatoria.";
    }
    
    // Si no hay errores, proceder con la actualización
    if (empty($errores)) {
        try {
            // Preparar datos
            $numeroSerie = $_POST['numero_serie'];
            $placas = $_POST['placas'];
            $marca = $_POST['marca'];
            $modelo = $_POST['modelo'];
            $anoFabricacion = (int) $_POST['ano_fabricacion'];
            $color = $_POST['color'];
            $numeroMotor = $_POST['numero_motor'];
            $tipoCarroceria = $_POST['tipo_carroceria'];
            $numeroAsientos = (int) $_POST['numero_asientos'];
            $cilindraje = (int) $_POST['cilindraje'];
            $tipoCombustible = $_POST['tipo_combustible'];
            $uso = $_POST['uso'];
            $transmision = $_POST['transmision'];
            $numeroPuertas = (int) $_POST['numero_puertas'];
            $clase = $_POST['clase'];
            
            // Actualizar en la base de datos
            $query = "
                UPDATE vehiculos SET
                    NumeroSerie = :numeroSerie,
                    Placas = :placas,
                    Marca = :marca,
                    Modelo = :modelo,
                    AnoFabricacion = :anoFabricacion,
                    Color = :color,
                    NumeroMotor = :numeroMotor,
                    TipoCarroceria = :tipoCarroceria,
                    NumeroAsientos = :numeroAsientos,
                    Cilindraje = :cilindraje,
                    TipoCombustible = :tipoCombustible,
                    Uso = :uso,
                    Transmision = :transmision,
                    NumeroPuertas = :numeroPuertas,
                    Clase = :clase
                WHERE ID_Vehiculo = :id
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':numeroSerie', $numeroSerie);
            $stmt->bindParam(':placas', $placas);
            $stmt->bindParam(':marca', $marca);
            $stmt->bindParam(':modelo', $modelo);
            $stmt->bindParam(':anoFabricacion', $anoFabricacion);
            $stmt->bindParam(':color', $color);
            $stmt->bindParam(':numeroMotor', $numeroMotor);
            $stmt->bindParam(':tipoCarroceria', $tipoCarroceria);
            $stmt->bindParam(':numeroAsientos', $numeroAsientos);
            $stmt->bindParam(':cilindraje', $cilindraje);
            $stmt->bindParam(':tipoCombustible', $tipoCombustible);
            $stmt->bindParam(':uso', $uso);
            $stmt->bindParam(':transmision', $transmision);
            $stmt->bindParam(':numeroPuertas', $numeroPuertas);
            $stmt->bindParam(':clase', $clase);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            if ($esVentanaEmergente) {
                // Si es ventana emergente, retornar éxito como JSON
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Vehículo actualizado correctamente.',
                    'vehiculo' => [
                        'id' => $id,
                        'descripcion' => "$marca $modelo ($anoFabricacion) - $placas"
                    ]
                ]);
                exit;
            } else {
                // Redirección normal
                $_SESSION['success'] = "Vehículo actualizado correctamente.";
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $errores[] = "Error en la base de datos: " . $e->getMessage();
        }
    }
    
    // Si hay errores y es ventana emergente, devolver errores en formato JSON
    if ($esVentanaEmergente && !empty($errores)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => $errores
        ]);
        exit;
    }
}

// Solo cargar header si no es ventana emergente
if (!$esVentanaEmergente) {
    require_once '../includes/header.php';
} else {
    // Estilos mínimos para popup
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Vehículo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
        <style>
            body { padding: 15px; }
            .container { width: 100%; max-width: 800px; }
        </style>
    </head>
    <body>';
}
?>

<div class="container <?php echo $esVentanaEmergente ? 'pt-0' : ''; ?>">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $esVentanaEmergente ? 'Editar Vehículo' : 'Editar Vehículo'; ?></h1>
        <?php if (!$esVentanaEmergente): ?>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($errores) && !$esVentanaEmergente): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" id="vehiculo-form">
                <div class="row">
                    <!-- Columna izquierda -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="numero_serie" class="form-label">Número de Serie <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_serie" name="numero_serie" required value="<?php echo htmlspecialchars($vehiculo['NumeroSerie']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="placas" class="form-label">Placas <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="placas" name="placas" required maxlength="10" value="<?php echo htmlspecialchars($vehiculo['Placas']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="marca" name="marca" required value="<?php echo htmlspecialchars($vehiculo['Marca']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="modelo" class="form-label">Modelo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modelo" name="modelo" required value="<?php echo htmlspecialchars($vehiculo['Modelo']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="ano_fabricacion" class="form-label">Año de Fabricación <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="ano_fabricacion" name="ano_fabricacion" required min="1900" max="<?php echo date('Y') + 1; ?>" value="<?php echo htmlspecialchars($vehiculo['AnoFabricacion']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="color" name="color" required value="<?php echo htmlspecialchars($vehiculo['Color']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="numero_motor" class="form-label">Número de Motor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_motor" name="numero_motor" required value="<?php echo htmlspecialchars($vehiculo['NumeroMotor']); ?>">
                        </div>
                    </div>
                    
                    <!-- Columna derecha -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="tipo_carroceria" class="form-label">Tipo de Carrocería <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tipo_carroceria" name="tipo_carroceria" required value="<?php echo htmlspecialchars($vehiculo['TipoCarroceria']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="numero_asientos" class="form-label">Número de Asientos <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="numero_asientos" name="numero_asientos" required min="1" max="100" value="<?php echo htmlspecialchars($vehiculo['NumeroAsientos']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="cilindraje" class="form-label">Cilindraje (cc) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="cilindraje" name="cilindraje" required min="1" value="<?php echo htmlspecialchars($vehiculo['Cilindraje']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="tipo_combustible" class="form-label">Tipo de Combustible <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipo_combustible" name="tipo_combustible" required>
                                <option value="">Seleccione tipo de combustible...</option>
                                <option value="Gasolina" <?php echo ($vehiculo['TipoCombustible'] === 'Gasolina') ? 'selected' : ''; ?>>Gasolina</option>
                                <option value="Diesel" <?php echo ($vehiculo['TipoCombustible'] === 'Diesel') ? 'selected' : ''; ?>>Diesel</option>
                                <option value="Eléctrico" <?php echo ($vehiculo['TipoCombustible'] === 'Eléctrico') ? 'selected' : ''; ?>>Eléctrico</option>
                                <option value="Híbrido" <?php echo ($vehiculo['TipoCombustible'] === 'Híbrido') ? 'selected' : ''; ?>>Híbrido</option>
                                <option value="Gas LP" <?php echo ($vehiculo['TipoCombustible'] === 'Gas LP') ? 'selected' : ''; ?>>Gas LP</option>
                                <option value="Gas Natural" <?php echo ($vehiculo['TipoCombustible'] === 'Gas Natural') ? 'selected' : ''; ?>>Gas Natural</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="uso" class="form-label">Uso <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="uso" name="uso" required value="<?php echo htmlspecialchars($vehiculo['Uso']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="transmision" class="form-label">Transmisión <span class="text-danger">*</span></label>
                            <select class="form-select" id="transmision" name="transmision" required>
                                <option value="">Seleccione tipo de transmisión...</option>
                                <option value="Manual" <?php echo ($vehiculo['Transmision'] === 'Manual') ? 'selected' : ''; ?>>Manual</option>
                                <option value="Automática" <?php echo ($vehiculo['Transmision'] === 'Automática') ? 'selected' : ''; ?>>Automática</option>
                                <option value="CVT" <?php echo ($vehiculo['Transmision'] === 'CVT') ? 'selected' : ''; ?>>CVT</option>
                                <option value="Semi-Automática" <?php echo ($vehiculo['Transmision'] === 'Semi-Automática') ? 'selected' : ''; ?>>Semi-Automática</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="numero_puertas" class="form-label">Número de Puertas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="numero_puertas" name="numero_puertas" required min="1" max="10" value="<?php echo htmlspecialchars($vehiculo['NumeroPuertas']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="clase" class="form-label">Clase <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="clase" name="clase" required value="<?php echo htmlspecialchars($vehiculo['Clase']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <?php if ($esVentanaEmergente): ?>
                        <button type="button" class="btn btn-secondary me-2" onclick="window.close();">Cancelar</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="bi bi-save"></i> Actualizar Vehículo
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($esVentanaEmergente): ?>
        <div class="alert alert-danger mt-3 d-none" id="error-container">
            <ul class="mb-0" id="error-list"></ul>
        </div>
    <?php endif; ?>
</div>

<?php if ($esVentanaEmergente): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('vehiculo-form');
    const errorContainer = document.getElementById('error-container');
    const errorList = document.getElementById('error-list');
    const submitBtn = document.getElementById('submit-btn');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Mostrar indicador de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Actualizando...';
        
        // Limpiar errores anteriores
        errorList.innerHTML = '';
        errorContainer.classList.add('d-none');
        
        // Obtener datos del formulario
        const formData = new FormData(form);
        
        // Enviar solicitud AJAX
        fetch('edit.php?id=<?php echo $id; ?>&popup=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Si hay éxito, pasar los datos al formulario padre y cerrar
                if (window.opener && !window.opener.closed) {
                    window.opener.updateVehiculo(data.vehiculo);
                }
                window.close();
            } else {
                // Mostrar errores
                data.errors.forEach(error => {
                    const li = document.createElement('li');
                    li.textContent = error;
                    errorList.appendChild(li);
                });
                errorContainer.classList.remove('d-none');
                
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Actualizar Vehículo';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const li = document.createElement('li');
            li.textContent = 'Error al procesar la solicitud. Inténtelo de nuevo.';
            errorList.appendChild(li);
            errorContainer.classList.remove('d-none');
            
            // Restaurar botón
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Actualizar Vehículo';
        });
    });
});
</script>
<?php endif; ?>

<?php
// Cerrar etiquetas HTML si es ventana emergente
if ($esVentanaEmergente) {
    echo '</body></html>';
} else {
    require_once '../includes/footer.php';
}
ob_end_flush();
?>