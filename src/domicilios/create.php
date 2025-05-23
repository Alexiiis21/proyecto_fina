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

// Variable para indicar si esta página fue llamada desde otro formulario
$esVentanaEmergente = isset($_GET['popup']) && $_GET['popup'] == 1;

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    }
    
    // Si no hay errores, proceder con la inserción
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
            
            // Insertar en la base de datos
            $query = "
                INSERT INTO domicilios (
                    Calle, NumeroExterior, NumeroInterior, Colonia, 
                    Municipio, Estado, Referencia, CodigoPostal
                ) VALUES (
                    :calle, :numeroExterior, :numeroInterior, :colonia, 
                    :municipio, :estado, :referencia, :codigoPostal
                )
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
            
            $stmt->execute();
            
            $nuevoId = $pdo->lastInsertId();
            
            if ($esVentanaEmergente) {
                // Si es ventana emergente, retornar el nuevo domicilio como JSON
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Domicilio registrado correctamente.',
                    'domicilio' => [
                        'id' => $nuevoId,
                        'direccion' => "$calle " . ($numeroExterior ? "#$numeroExterior" : "") . ", $colonia, $municipio, $estado, CP: $codigoPostal"
                    ]
                ]);
                exit;
            } else {
                // Redirección normal
                $_SESSION['success'] = "Domicilio registrado correctamente.";
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
        <title>Registrar Domicilio</title>
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
        <h1><?php echo $esVentanaEmergente ? 'Registrar Nuevo Domicilio' : 'Registrar Domicilio'; ?></h1>
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
                            <input type="text" class="form-control" id="calle" name="calle" required value="<?php echo isset($_POST['calle']) ? htmlspecialchars($_POST['calle']) : ''; ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero_exterior" class="form-label">Número Exterior</label>
                                    <input type="text" class="form-control" id="numero_exterior" name="numero_exterior" value="<?php echo isset($_POST['numero_exterior']) ? htmlspecialchars($_POST['numero_exterior']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="numero_interior" class="form-label">Número Interior</label>
                                    <input type="text" class="form-control" id="numero_interior" name="numero_interior" value="<?php echo isset($_POST['numero_interior']) ? htmlspecialchars($_POST['numero_interior']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="colonia" class="form-label">Colonia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="colonia" name="colonia" required value="<?php echo isset($_POST['colonia']) ? htmlspecialchars($_POST['colonia']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="municipio" class="form-label">Municipio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="municipio" name="municipio" required value="<?php echo isset($_POST['municipio']) ? htmlspecialchars($_POST['municipio']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="">Seleccione un estado...</option>
                                <option value="Aguascalientes" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Aguascalientes') ? 'selected' : ''; ?>>Aguascalientes</option>
                                <option value="Baja California" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Baja California') ? 'selected' : ''; ?>>Baja California</option>
                                <option value="Baja California Sur" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Baja California Sur') ? 'selected' : ''; ?>>Baja California Sur</option>
                                <option value="Campeche" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Campeche') ? 'selected' : ''; ?>>Campeche</option>
                                <option value="Chiapas" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Chiapas') ? 'selected' : ''; ?>>Chiapas</option>
                                <option value="Chihuahua" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Chihuahua') ? 'selected' : ''; ?>>Chihuahua</option>
                                <option value="Ciudad de México" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Ciudad de México') ? 'selected' : ''; ?>>Ciudad de México</option>
                                <option value="Coahuila" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Coahuila') ? 'selected' : ''; ?>>Coahuila</option>
                                <option value="Colima" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Colima') ? 'selected' : ''; ?>>Colima</option>
                                <option value="Durango" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Durango') ? 'selected' : ''; ?>>Durango</option>
                                <option value="Estado de México" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Estado de México') ? 'selected' : ''; ?>>Estado de México</option>
                                <option value="Guanajuato" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Guanajuato') ? 'selected' : ''; ?>>Guanajuato</option>
                                <option value="Guerrero" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Guerrero') ? 'selected' : ''; ?>>Guerrero</option>
                                <option value="Hidalgo" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Hidalgo') ? 'selected' : ''; ?>>Hidalgo</option>
                                <option value="Jalisco" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Jalisco') ? 'selected' : ''; ?>>Jalisco</option>
                                <option value="Michoacán" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Michoacán') ? 'selected' : ''; ?>>Michoacán</option>
                                <option value="Morelos" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Morelos') ? 'selected' : ''; ?>>Morelos</option>
                                <option value="Nayarit" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Nayarit') ? 'selected' : ''; ?>>Nayarit</option>
                                <option value="Nuevo León" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Nuevo León') ? 'selected' : ''; ?>>Nuevo León</option>
                                <option value="Oaxaca" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Oaxaca') ? 'selected' : ''; ?>>Oaxaca</option>
                                <option value="Puebla" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Puebla') ? 'selected' : ''; ?>>Puebla</option>
                                <option value="Querétaro" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Querétaro') ? 'selected' : ''; ?>>Querétaro</option>
                                <option value="Quintana Roo" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Quintana Roo') ? 'selected' : ''; ?>>Quintana Roo</option>
                                <option value="San Luis Potosí" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'San Luis Potosí') ? 'selected' : ''; ?>>San Luis Potosí</option>
                                <option value="Sinaloa" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Sinaloa') ? 'selected' : ''; ?>>Sinaloa</option>
                                <option value="Sonora" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Sonora') ? 'selected' : ''; ?>>Sonora</option>
                                <option value="Tabasco" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Tabasco') ? 'selected' : ''; ?>>Tabasco</option>
                                <option value="Tamaulipas" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Tamaulipas') ? 'selected' : ''; ?>>Tamaulipas</option>
                                <option value="Tlaxcala" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Tlaxcala') ? 'selected' : ''; ?>>Tlaxcala</option>
                                <option value="Veracruz" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Veracruz') ? 'selected' : ''; ?>>Veracruz</option>
                                <option value="Yucatán" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Yucatán') ? 'selected' : ''; ?>>Yucatán</option>
                                <option value="Zacatecas" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Zacatecas') ? 'selected' : ''; ?>>Zacatecas</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="codigo_postal" class="form-label">Código Postal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" required maxlength="5" value="<?php echo isset($_POST['codigo_postal']) ? htmlspecialchars($_POST['codigo_postal']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="referencia" class="form-label">Referencia</label>
                            <textarea class="form-control" id="referencia" name="referencia" rows="2"><?php echo isset($_POST['referencia']) ? htmlspecialchars($_POST['referencia']) : ''; ?></textarea>
                            <div class="form-text">Información adicional para facilitar la localización.</div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <?php if ($esVentanaEmergente): ?>
                        <button type="button" class="btn btn-secondary me-2" onclick="window.close();">Cancelar</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="bi bi-save"></i> Guardar Domicilio
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
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
        
        // Limpiar errores anteriores
        errorList.innerHTML = '';
        errorContainer.classList.add('d-none');
        
        // Obtener datos del formulario
        const formData = new FormData(form);
        
        // Enviar solicitud AJAX
        fetch('create.php?popup=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Si hay éxito, pasar los datos al formulario padre y cerrar
                if (window.opener && !window.opener.closed) {
                    window.opener.addDomicilio(data.domicilio);
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
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Guardar Domicilio';
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
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Guardar Domicilio';
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