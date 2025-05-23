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


// Obtener lista de licencias para el select
try {
    $stmt = $pdo->query("SELECT ID_Licencia, NumeroLicencia FROM licencias ORDER BY NumeroLicencia");
    $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $licencias = [];
}

// Obtener lista de domicilios para el select
try {
    $stmt = $pdo->query("SELECT ID_Domicilio, CONCAT(Calle, ' ', NumeroExterior, ', ', Colonia, ', ', Municipio, ', ', Estado) AS DireccionCompleta FROM domicilios ORDER BY Estado, Municipio, Colonia");
    $domicilios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $domicilios = [];
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errores = [];
    
    // Validar campos obligatorios
    if (empty($_POST['nombre'])) {
        $errores[] = "El nombre del conductor es obligatorio.";
    }
    if (empty($_POST['curp'])) {
        $errores[] = "El CURP es obligatorio.";
    }
    if (empty($_POST['rfc'])) {
        $errores[] = "El RFC es obligatorio.";
    }
    if (empty($_POST['telefono'])) {
        $errores[] = "El teléfono es obligatorio.";
    }
    if (empty($_POST['correo_electronico'])) {
        $errores[] = "El correo electrónico es obligatorio.";
    }
    if (empty($_POST['id_domicilio'])) {
        $errores[] = "Debe seleccionar un domicilio.";
    }
    
    // Si no hay errores, proceder con la inserción
    if (empty($errores)) {
        try {
            $nombre = $_POST['nombre'];
            $curp = $_POST['curp'];
            $rfc = $_POST['rfc'];
            $telefono = $_POST['telefono'];
            $correoElectronico = $_POST['correo_electronico'];
            $idDomicilio = (int) $_POST['id_domicilio'];
            $licencia = !empty($_POST['licencia']) ? (int) $_POST['licencia'] : NULL;
            
            $imagenPerfil = null;
            $firma = null;
            
            if (!file_exists("../uploads/fotos")) {
                mkdir("../uploads/fotos", 0777, true);
            }
            
            if (!file_exists("../uploads/firmas")) {
                mkdir("../uploads/firmas", 0777, true);
            }
            
            if (isset($_FILES['imagen_perfil']) && $_FILES['imagen_perfil']['error'] === UPLOAD_ERR_OK) {
                $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
                
                if (!in_array($_FILES['imagen_perfil']['type'], $tiposPermitidos)) {
                    $errores[] = "El formato de la imagen de perfil no es válido. Se permiten: jpg, jpeg, png.";
                } else {
                    $extension = pathinfo($_FILES['imagen_perfil']['name'], PATHINFO_EXTENSION);
                    $nuevoNombre = uniqid() . '_perfil.' . $extension;
                    
                    // Ruta de destino
                    $destino = "../uploads/fotos/" . $nuevoNombre;
                    
                    if (move_uploaded_file($_FILES['imagen_perfil']['tmp_name'], $destino)) {
                        $imagenPerfil = $nuevoNombre;
                    } else {
                        $errores[] = "Error al subir la imagen de perfil.";
                    }
                }
            }
            
            // Procesar firma
            if (isset($_FILES['firma']) && $_FILES['firma']['error'] === UPLOAD_ERR_OK) {
                $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
                
                if (!in_array($_FILES['firma']['type'], $tiposPermitidos)) {
                    $errores[] = "El formato de la firma no es válido. Se permiten: jpg, jpeg, png.";
                } else {
                    $extension = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
                    $nuevoNombre = uniqid() . '_firma.' . $extension;
                    
                    $destino = "../uploads/firmas/" . $nuevoNombre;
                    
                    if (move_uploaded_file($_FILES['firma']['tmp_name'], $destino)) {
                        $firma = $nuevoNombre;
                    } else {
                        $errores[] = "Error al subir la firma.";
                    }
                }
            }
            
            // Si no hay errores en la carga de archivos, insertar en la base de datos
            if (empty($errores)) {
                // Construir la consulta SQL basada en si la licencia tiene valor o no
                if ($licencia !== NULL) {
                    $query = "
                        INSERT INTO conductores (
                            Nombre, CURP, RFC, Telefono, CorreoElectronico, 
                            ID_Domicilio, Licencia, ImagenPerfil, Firma
                        ) VALUES (
                            :nombre, :curp, :rfc, :telefono, :correoElectronico, 
                            :idDomicilio, :licencia, :imagenPerfil, :firma
                        )
                    ";
                } else {
                    $query = "
                        INSERT INTO conductores (
                            Nombre, CURP, RFC, Telefono, CorreoElectronico, 
                            ID_Domicilio, ImagenPerfil, Firma
                        ) VALUES (
                            :nombre, :curp, :rfc, :telefono, :correoElectronico, 
                            :idDomicilio, :imagenPerfil, :firma
                        )
                    ";
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':curp', $curp);
                $stmt->bindParam(':rfc', $rfc);
                $stmt->bindParam(':telefono', $telefono);
                $stmt->bindParam(':correoElectronico', $correoElectronico);
                $stmt->bindParam(':idDomicilio', $idDomicilio);
                if ($licencia !== NULL) {
                    $stmt->bindParam(':licencia', $licencia);
                }
                $stmt->bindParam(':imagenPerfil', $imagenPerfil);
                $stmt->bindParam(':firma', $firma);
                
                $stmt->execute();
                
                $_SESSION['success'] = "Conductor registrado correctamente.";
                header("Location: index.php");
                exit;
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
        <h1>Registrar Nuevo Conductor</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    
    <?php if (!empty($errores)): ?>
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
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <!-- Datos básicos del conductor -->
                    <div class="col-md-6">
                        <h3 class="mb-3">Información Personal</h3>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="curp" class="form-label">CURP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="curp" name="curp" required maxlength="18" value="<?php echo isset($_POST['curp']) ? htmlspecialchars($_POST['curp']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="rfc" class="form-label">RFC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rfc" name="rfc" required maxlength="13" value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="correo_electronico" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required value="<?php echo isset($_POST['correo_electronico']) ? htmlspecialchars($_POST['correo_electronico']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h3 class="mb-3">Información Adicional</h3>
                        
                    <div class="mb-3">
    <label for="id_domicilio" class="form-label">Domicilio <span class="text-danger">*</span></label>
    <div class="input-group">
        <select class="form-select" id="id_domicilio" name="id_domicilio" required>
            <option value="">Seleccione un domicilio...</option>
            <?php foreach ($domicilios as $domicilio): ?>
                <option value="<?php echo $domicilio['ID_Domicilio']; ?>" <?php echo (isset($_POST['id_domicilio']) && $_POST['id_domicilio'] == $domicilio['ID_Domicilio']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($domicilio['DireccionCompleta']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-primary" onclick="openDomicilioPopup()">
            <i class="bi bi-plus-circle"></i> Nuevo
        </button>
    </div>
    <div class="form-text" id="domicilio-help">
        Seleccione un domicilio existente o cree uno nuevo con el botón.
    </div>
</div>
                        
                        <div class="mb-3">
                            <label for="licencia" class="form-label">Licencia</label>
                            <select class="form-select" id="licencia" name="licencia">
                                <option value="">Sin licencia asignada</option>
                                <?php foreach ($licencias as $licencia): ?>
                                    <option value="<?php echo $licencia['ID_Licencia']; ?>" <?php echo (isset($_POST['licencia']) && $_POST['licencia'] == $licencia['ID_Licencia']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($licencia['NumeroLicencia']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="imagen_perfil" class="form-label">Foto de Perfil</label>
                            <input type="file" class="form-control" id="imagen_perfil" name="imagen_perfil" accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">Formatos permitidos: jpg, jpeg, png. Tamaño máximo: 2MB.</div>
                            
                            <div class="mt-2 d-none" id="imagen_preview_container">
                                <img id="imagen_preview" src="#" alt="Vista previa" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="firma" class="form-label">Firma</label>
                            <input type="file" class="form-control" id="firma" name="firma" accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">Formatos permitidos: jpg, jpeg, png. Tamaño máximo: 2MB.</div>
                            
                            <div class="mt-2 d-none" id="firma_preview_container">
                                <img id="firma_preview" src="#" alt="Vista previa de firma" class="img-thumbnail" style="max-width: 200px; max-height: 100px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Conductor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funciones para gestión de domicilios en ventana emergente
function openDomicilioPopup() {
    // Abrir ventana emergente
    const popupWindow = window.open('../domicilios/create.php?popup=1', 'nuevoDomicilio', 'width=800,height=600,scrollbars=yes');
    
    // Verificar si la ventana se abrió correctamente
    if (popupWindow) {
        popupWindow.focus();
    } else {
        alert('La ventana emergente fue bloqueada por el navegador. Por favor, permita ventanas emergentes para este sitio.');
    }
}

// Función para recibir el nuevo domicilio desde la ventana emergente
function addDomicilio(domicilio) {
    const select = document.getElementById('id_domicilio');
    
    // Crear una nueva opción para el select
    const option = document.createElement('option');
    option.value = domicilio.id;
    option.text = domicilio.direccion;
    option.selected = true;
    
    // Agregar la opción al select
    select.appendChild(option);
    
    // Opcional: mostrar mensaje de éxito temporal
    const helpText = document.getElementById('domicilio-help');
    const originalText = helpText.innerHTML;
    
    helpText.innerHTML = '<span class="text-success">¡Domicilio agregado correctamente!</span>';
    helpText.classList.add('text-success');
    
    setTimeout(() => {
        helpText.innerHTML = originalText;
        helpText.classList.remove('text-success');
    }, 3000);
}

// Script para mostrar previsualizaciones de imágenes
document.addEventListener('DOMContentLoaded', function() {
    // Previsualizar imagen de perfil
    document.getElementById('imagen_perfil').addEventListener('change', function(e) {
        const previewContainer = document.getElementById('imagen_preview_container');
        const preview = document.getElementById('imagen_preview');
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.classList.remove('d-none');
            }
            
            reader.readAsDataURL(this.files[0]);
        } else {
            previewContainer.classList.add('d-none');
        }
    });
    
    document.getElementById('firma').addEventListener('change', function(e) {
        const previewContainer = document.getElementById('firma_preview_container');
        const preview = document.getElementById('firma_preview');
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.classList.remove('d-none');
            }
            
            reader.readAsDataURL(this.files[0]);
        } else {
            previewContainer.classList.add('d-none');
        }
    });
    
    window.addEventListener('focus', function() {
    });
});
window.addDomicilio = addDomicilio;
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>

