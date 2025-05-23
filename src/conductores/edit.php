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

// Verificar ID del conductor
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de conductor no válido.";
    header("Location: index.php");
    exit;
}

$id_conductor = (int) $_GET['id'];

try {
    $stmt = $pdo->query("SELECT ID_Licencia, NumeroLicencia FROM licencias ORDER BY NumeroLicencia");
    $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $licencias = [];
}

try {
    $stmt = $pdo->query("SELECT ID_Domicilio, CONCAT(Calle, ' ', NumeroExterior, ', ', Colonia, ', ', Municipio, ', ', Estado) AS DireccionCompleta FROM domicilios ORDER BY Estado, Municipio, Colonia");
    $domicilios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $domicilios = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM conductores WHERE ID_Conductor = ?");
    $stmt->execute([$id_conductor]);
    $conductor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conductor) {
        $_SESSION['error'] = "Conductor no encontrado.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al recuperar datos del conductor: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

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
    
    // Si no hay errores, proceder con la actualización
    if (empty($errores)) {
        try {
            // Preparar datos
            $nombre = $_POST['nombre'];
            $curp = $_POST['curp'];
            $rfc = $_POST['rfc'];
            $telefono = $_POST['telefono'];
            $correoElectronico = $_POST['correo_electronico'];
            $idDomicilio = (int) $_POST['id_domicilio'];
            $licencia = !empty($_POST['licencia']) ? (int) $_POST['licencia'] : NULL;
            
            $imagenPerfil = $conductor['ImagenPerfil']; 
            $firma = $conductor['Firma']; 
            
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
                    
                    $destino = "../uploads/fotos/" . $nuevoNombre;
                    
                    if (move_uploaded_file($_FILES['imagen_perfil']['tmp_name'], $destino)) {
                        if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])) {
                            unlink("../uploads/fotos/" . $conductor['ImagenPerfil']);
                        }
                        $imagenPerfil = $nuevoNombre;
                    } else {
                        $errores[] = "Error al subir la imagen de perfil.";
                    }
                }
            } elseif (isset($_POST['eliminar_imagen_perfil']) && $_POST['eliminar_imagen_perfil'] == 1) {
                if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])) {
                    unlink("../uploads/fotos/" . $conductor['ImagenPerfil']);
                }
                $imagenPerfil = null;
            }
            
            if (isset($_FILES['firma']) && $_FILES['firma']['error'] === UPLOAD_ERR_OK) {
                $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png'];
                
                if (!in_array($_FILES['firma']['type'], $tiposPermitidos)) {
                    $errores[] = "El formato de la firma no es válido. Se permiten: jpg, jpeg, png.";
                } else {
                    $extension = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
                    $nuevoNombre = uniqid() . '_firma.' . $extension;
                    
                    $destino = "../uploads/firmas/" . $nuevoNombre;
                    
                    if (move_uploaded_file($_FILES['firma']['tmp_name'], $destino)) {
                        // Si hay una firma anterior, eliminarla
                        if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])) {
                            unlink("../uploads/firmas/" . $conductor['Firma']);
                        }
                        $firma = $nuevoNombre;
                    } else {
                        $errores[] = "Error al subir la firma.";
                    }
                }
            } elseif (isset($_POST['eliminar_firma']) && $_POST['eliminar_firma'] == 1) {
                if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])) {
                    unlink("../uploads/firmas/" . $conductor['Firma']);
                }
                $firma = null;
            }
            
            if (empty($errores)) {
                $query = "
                    UPDATE conductores 
                    SET Nombre = :nombre, 
                        CURP = :curp, 
                        RFC = :rfc, 
                        Telefono = :telefono, 
                        CorreoElectronico = :correoElectronico, 
                        ID_Domicilio = :idDomicilio, 
                        ImagenPerfil = :imagenPerfil, 
                        Firma = :firma
                ";
                
                if ($licencia !== NULL) {
                    $query .= ", Licencia = :licencia";
                } else {
                    $query .= ", Licencia = NULL";
                }
                
                $query .= " WHERE ID_Conductor = :idConductor";
                
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':curp', $curp);
                $stmt->bindParam(':rfc', $rfc);
                $stmt->bindParam(':telefono', $telefono);
                $stmt->bindParam(':correoElectronico', $correoElectronico);
                $stmt->bindParam(':idDomicilio', $idDomicilio);
                $stmt->bindParam(':imagenPerfil', $imagenPerfil);
                $stmt->bindParam(':firma', $firma);
                $stmt->bindParam(':idConductor', $id_conductor);
                
                if ($licencia !== NULL) {
                    $stmt->bindParam(':licencia', $licencia);
                }
                
                $stmt->execute();
                
                $_SESSION['success'] = "Conductor actualizado correctamente.";
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
        <h1>Editar Conductor</h1>
        <div>
            <a href="view.php?id=<?php echo $id_conductor; ?>" class="btn btn-info me-2">
                <i class="bi bi-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
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
                            <input type="text" class="form-control" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($conductor['Nombre']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="curp" class="form-label">CURP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="curp" name="curp" required maxlength="18" value="<?php echo htmlspecialchars($conductor['CURP']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="rfc" class="form-label">RFC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rfc" name="rfc" required maxlength="13" value="<?php echo htmlspecialchars($conductor['RFC']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required value="<?php echo htmlspecialchars($conductor['Telefono']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="correo_electronico" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="correo_electronico" name="correo_electronico" required value="<?php echo htmlspecialchars($conductor['CorreoElectronico']); ?>">
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
                                        <option value="<?php echo $domicilio['ID_Domicilio']; ?>" <?php echo ($conductor['ID_Domicilio'] == $domicilio['ID_Domicilio']) ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo $licencia['ID_Licencia']; ?>" <?php echo ($conductor['Licencia'] == $licencia['ID_Licencia']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($licencia['NumeroLicencia']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="imagen_perfil" class="form-label">Foto de Perfil</label>
                            <?php if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])): ?>
                                <div class="mb-2">
                                    <img src="../uploads/fotos/<?php echo $conductor['ImagenPerfil']; ?>" alt="Foto actual" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="eliminar_imagen_perfil" name="eliminar_imagen_perfil" value="1">
                                        <label class="form-check-label text-danger" for="eliminar_imagen_perfil">
                                            Eliminar foto actual
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="imagen_perfil" name="imagen_perfil" accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">Formatos permitidos: jpg, jpeg, png. Tamaño máximo: 2MB.</div>
                            
                            <div class="mt-2 d-none" id="imagen_preview_container">
                                <img id="imagen_preview" src="#" alt="Vista previa" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="firma" class="form-label">Firma</label>
                            <?php if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])): ?>
                                <div class="mb-2">
                                    <img src="../uploads/firmas/<?php echo $conductor['Firma']; ?>" alt="Firma actual" class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="eliminar_firma" name="eliminar_firma" value="1">
                                        <label class="form-check-label text-danger" for="eliminar_firma">
                                            Eliminar firma actual
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="firma" name="firma" accept="image/jpeg,image/jpg,image/png">
                            <div class="form-text">Formatos permitidos: jpg, jpeg, png. Tamaño máximo: 2MB.</div>
                            
                            <div class="mt-2 d-none" id="firma_preview_container">
                                <img id="firma_preview" src="#" alt="Vista previa de firma" class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-end mt-3">
                    <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
            
            // Desmarcar checkbox de eliminar si selecciona una nueva imagen
            if (document.getElementById('eliminar_imagen_perfil')) {
                document.getElementById('eliminar_imagen_perfil').checked = false;
            }
        } else {
            previewContainer.classList.add('d-none');
        }
    });
    
    // Previsualizar firma
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
            
            // Desmarcar checkbox de eliminar si selecciona una nueva firma
            if (document.getElementById('eliminar_firma')) {
                document.getElementById('eliminar_firma').checked = false;
            }
        } else {
            previewContainer.classList.add('d-none');
        }
    });
    
    // Funciones para gestión de domicilios en ventana emergente
    window.openDomicilioPopup = function() {
        // Abrir ventana emergente
        const popupWindow = window.open('../domicilios/create.php?popup=1', 'nuevoDomicilio', 'width=800,height=600,scrollbars=yes');
        
        // Verificar si la ventana se abrió correctamente
        if (popupWindow) {
            popupWindow.focus();
        } else {
            alert('La ventana emergente fue bloqueada por el navegador. Por favor, permita ventanas emergentes para este sitio.');
        }
    };
    
    // Función para recibir el nuevo domicilio desde la ventana emergente
    window.addDomicilio = function(domicilio) {
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
    };
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>