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
            'errors' => ["ID de domicilio no válido."]
        ]);
        exit;
    } else {
        $_SESSION['error'] = "ID de domicilio no válido.";
        header("Location: index.php");
        exit;
    }
}

$id = (int) $_GET['id'];

// Obtener datos del domicilio
try {
    $stmt = $pdo->prepare("SELECT * FROM domicilios WHERE ID_Domicilio = ?");
    $stmt->execute([$id]);
    $domicilio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$domicilio) {
        if ($esVentanaEmergente) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'errors' => ["Domicilio no encontrado."]
            ]);
            exit;
        } else {
            $_SESSION['error'] = "Domicilio no encontrado.";
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
    if (empty($_POST['calle'])) {
        $errores[] = "La calle es obligatoria.";
    }
    if (empty($_POST['colonia'])) {
        $errores[] = "La colonia es obligatoria.";
    }
    if (empty($_POST['municipio'])) {
        $errores[] = "El municipio es obligatorio.";
    }
    if (empty($_POST['estado'])) {
        $errores[] = "El estado es obligatorio.";
    }
    if (empty($_POST['codigo_postal'])) {
        $errores[] = "El código postal es obligatorio.";
    } elseif (!is_numeric($_POST['codigo_postal'])) {
        $errores[] = "El código postal debe ser numérico.";
    } elseif (strlen($_POST['codigo_postal']) > 5) {
        $errores[] = "El código postal no puede tener más de 5 dígitos.";
    }
    
    // Si no hay errores, proceder con la actualización
    if (empty($errores)) {
        try {
            // Preparar datos
            $calle = $_POST['calle'];
            $numeroExterior = !empty($_POST['numero_exterior']) ? $_POST['numero_exterior'] : null;
            $numeroInterior = !empty($_POST['numero_interior']) ? $_POST['numero_interior'] : null;
            $colonia = $_POST['colonia'];
            $municipio = $_POST['municipio'];
            $estado = $_POST['estado'];
            $referencia = !empty($_POST['referencia']) ? $_POST['referencia'] : null;
            $codigoPostal = (int) $_POST['codigo_postal'];
            
            // Actualizar en la base de datos
            $query = "
                UPDATE domicilios SET
                    Calle = :calle,
                    NumeroExterior = :numeroExterior,
                    NumeroInterior = :numeroInterior,
                    Colonia = :colonia,
                    Municipio = :municipio,
                    Estado = :estado,
                    Referencia = :referencia,
                    CodigoPostal = :codigoPostal
                WHERE ID_Domicilio = :id
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':calle', $calle);
            $stmt->bindParam(':numeroExterior', $numeroExterior);
            $stmt->bindParam(':numeroInterior', $numeroInterior);
            $stmt->bindParam(':colonia', $colonia);
            $stmt->bindParam(':municipio', $municipio);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':referencia', $referencia);
            $stmt->bindParam(':codigoPostal', $codigoPostal);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            if ($esVentanaEmergente) {
                // Si es ventana emergente, retornar éxito como JSON
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Domicilio actualizado correctamente.',
                    'domicilio' => [
                        'id' => $id,
                        'direccion' => "$calle " . ($numeroExterior ? "#$numeroExterior" : "") . ", $colonia, $municipio, $estado, CP: $codigoPostal"
                    ]
                ]);
                exit;
            } else {
                // Redirección normal
                $_SESSION['success'] = "Domicilio actualizado correctamente.";
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
        <title>Editar Domicilio</title>
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
        <h1><?php echo $esVentanaEmergente ? 'Editar Domicilio' : 'Editar Domicilio'; ?></h1>
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
            <form method="POST" id="domicilio-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="calle" class="form-label">Calle <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="calle" name="calle" required value="<?php echo htmlspecialchars($domicilio['Calle']); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero_exterior" class="form-label">Número Exterior</label>
                                    <input type="text" class="form-control" id="numero_exterior" name="numero_exterior" value="<?php echo htmlspecialchars($domicilio['NumeroExterior'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero_interior" class="form-label">Número Interior</label>
                                    <input type="text" class="form-control" id="numero_interior" name="numero_interior" value="<?php echo htmlspecialchars($domicilio['NumeroInterior'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="colonia" class="form-label">Colonia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="colonia" name="colonia" required value="<?php echo htmlspecialchars($domicilio['Colonia']); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="municipio" class="form-label">Municipio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="municipio" name="municipio" required value="<?php echo htmlspecialchars($domicilio['Municipio']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Seleccione un estado...</option>
                                <option value="Aguascalientes" <?php echo ($domicilio['Estado'] === 'Aguascalientes') ? 'selected' : ''; ?>>Aguascalientes</option>
                                <option value="Baja California" <?php echo ($domicilio['Estado'] === 'Baja California') ? 'selected' : ''; ?>>Baja California</option>
                                <option value="Baja California Sur" <?php echo ($domicilio['Estado'] === 'Baja California Sur') ? 'selected' : ''; ?>>Baja California Sur</option>
                                <option value="Campeche" <?php echo ($domicilio['Estado'] === 'Campeche') ? 'selected' : ''; ?>>Campeche</option>
                                <option value="Chiapas" <?php echo ($domicilio['Estado'] === 'Chiapas') ? 'selected' : ''; ?>>Chiapas</option>
                                <option value="Chihuahua" <?php echo ($domicilio['Estado'] === 'Chihuahua') ? 'selected' : ''; ?>>Chihuahua</option>
                                <option value="Ciudad de México" <?php echo ($domicilio['Estado'] === 'Ciudad de México') ? 'selected' : ''; ?>>Ciudad de México</option>
                                <option value="Coahuila" <?php echo ($domicilio['Estado'] === 'Coahuila') ? 'selected' : ''; ?>>Coahuila</option>
                                <option value="Colima" <?php echo ($domicilio['Estado'] === 'Colima') ? 'selected' : ''; ?>>Colima</option>
                                <option value="Durango" <?php echo ($domicilio['Estado'] === 'Durango') ? 'selected' : ''; ?>>Durango</option>
                                <option value="Estado de México" <?php echo ($domicilio['Estado'] === 'Estado de México') ? 'selected' : ''; ?>>Estado de México</option>
                                <option value="Guanajuato" <?php echo ($domicilio['Estado'] === 'Guanajuato') ? 'selected' : ''; ?>>Guanajuato</option>
                                <option value="Guerrero" <?php echo ($domicilio['Estado'] === 'Guerrero') ? 'selected' : ''; ?>>Guerrero</option>
                                <option value="Hidalgo" <?php echo ($domicilio['Estado'] === 'Hidalgo') ? 'selected' : ''; ?>>Hidalgo</option>
                                <option value="Jalisco" <?php echo ($domicilio['Estado'] === 'Jalisco') ? 'selected' : ''; ?>>Jalisco</option>
                                <option value="Michoacán" <?php echo ($domicilio['Estado'] === 'Michoacán') ? 'selected' : ''; ?>>Michoacán</option>
                                <option value="Morelos" <?php echo ($domicilio['Estado'] === 'Morelos') ? 'selected' : ''; ?>>Morelos</option>
                                <option value="Nayarit" <?php echo ($domicilio['Estado'] === 'Nayarit') ? 'selected' : ''; ?>>Nayarit</option>
                                <option value="Nuevo León" <?php echo ($domicilio['Estado'] === 'Nuevo León') ? 'selected' : ''; ?>>Nuevo León</option>
                                <option value="Oaxaca" <?php echo ($domicilio['Estado'] === 'Oaxaca') ? 'selected' : ''; ?>>Oaxaca</option>
                                <option value="Puebla" <?php echo ($domicilio['Estado'] === 'Puebla') ? 'selected' : ''; ?>>Puebla</option>
                                <option value="Querétaro" <?php echo ($domicilio['Estado'] === 'Querétaro') ? 'selected' : ''; ?>>Querétaro</option>
                                <option value="Quintana Roo" <?php echo ($domicilio['Estado'] === 'Quintana Roo') ? 'selected' : ''; ?>>Quintana Roo</option>
                                <option value="San Luis Potosí" <?php echo ($domicilio['Estado'] === 'San Luis Potosí') ? 'selected' : ''; ?>>San Luis Potosí</option>
                                <option value="Sinaloa" <?php echo ($domicilio['Estado'] === 'Sinaloa') ? 'selected' : ''; ?>>Sinaloa</option>
                                <option value="Sonora" <?php echo ($domicilio['Estado'] === 'Sonora') ? 'selected' : ''; ?>>Sonora</option>
                                <option value="Tabasco" <?php echo ($domicilio['Estado'] === 'Tabasco') ? 'selected' : ''; ?>>Tabasco</option>
                                <option value="Tamaulipas" <?php echo ($domicilio['Estado'] === 'Tamaulipas') ? 'selected' : ''; ?>>Tamaulipas</option>
                                <option value="Tlaxcala" <?php echo ($domicilio['Estado'] === 'Tlaxcala') ? 'selected' : ''; ?>>Tlaxcala</option>
                                <option value="Veracruz" <?php echo ($domicilio['Estado'] === 'Veracruz') ? 'selected' : ''; ?>>Veracruz</option>
                                <option value="Yucatán" <?php echo ($domicilio['Estado'] === 'Yucatán') ? 'selected' : ''; ?>>Yucatán</option>
                                <option value="Zacatecas" <?php echo ($domicilio['Estado'] === 'Zacatecas') ? 'selected' : ''; ?>>Zacatecas</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="codigo_postal" class="form-label">Código Postal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" required maxlength="5" value="<?php echo htmlspecialchars($domicilio['CodigoPostal']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="referencia" class="form-label">Referencia</label>
                            <textarea class="form-control" id="referencia" name="referencia" rows="3"><?php echo htmlspecialchars($domicilio['Referencia'] ?? ''); ?></textarea>
                            <div class="form-text">Información adicional para facilitar la localización.</div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <?php if ($esVentanaEmergente): ?>
                        <button type="button" class="btn btn-secondary me-2" onclick="window.close();">Cancelar</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="bi bi-save"></i> Actualizar Domicilio
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
    const form = document.getElementById('domicilio-form');
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
                    window.opener.updateDomicilio(data.domicilio);
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
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Actualizar Domicilio';
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
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Actualizar Domicilio';
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