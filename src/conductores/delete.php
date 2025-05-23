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

// Obtener datos del conductor y verificar si tiene licencias
try {
    // Comprobar si tiene licencias asociadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM licencias WHERE ID_Conductor = ?");
    $stmt->execute([$id_conductor]);
    $tieneLicencias = $stmt->fetchColumn() > 0;
    
    // Obtener datos del conductor para mostrar
    $query = "
        SELECT c.*, 
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio, d.Estado, d.CodigoPostal, d.Referencia,
               l.NumeroLicencia, l.FechaExpedicion, l.FechaVencimiento, l.TipoLicencia, l.Restricciones
        FROM conductores c
        LEFT JOIN domicilios d ON c.ID_Domicilio = d.ID_Domicilio
        LEFT JOIN licencias l ON c.Licencia = l.ID_Licencia
        WHERE c.ID_Conductor = ?
    ";
    
    $stmt = $pdo->prepare($query);
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

// Si se está confirmando la eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_eliminar'])) {
    try {
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Si tiene licencias asociadas y solicita eliminarlas (opción forzada)
        if ($tieneLicencias && isset($_POST['eliminar_licencias']) && $_POST['eliminar_licencias'] == 1) {
            // Eliminar primero las licencias asociadas
            $stmt = $pdo->prepare("DELETE FROM licencias WHERE ID_Conductor = ?");
            $stmt->execute([$id_conductor]);
        } else if ($tieneLicencias) {
            // Si tiene licencias pero no solicita eliminarlas, abortar
            throw new PDOException("No se puede eliminar el conductor porque tiene licencias asociadas.");
        }
        
        // Eliminar imágenes asociadas
        if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])) {
            unlink("../uploads/fotos/" . $conductor['ImagenPerfil']);
        }
        
        if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])) {
            unlink("../uploads/firmas/" . $conductor['Firma']);
        }
        
        // Eliminar el registro del conductor
        $stmt = $pdo->prepare("DELETE FROM conductores WHERE ID_Conductor = ?");
        $stmt->execute([$id_conductor]);
        
        // Confirmar transacción
        $pdo->commit();
        
        $_SESSION['success'] = "Conductor eliminado correctamente.";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        // Revertir en caso de error
        $pdo->rollBack();
        $_SESSION['error'] = "Error al eliminar el conductor: " . $e->getMessage();
        
        // Redireccionar de nuevo a la página de eliminar para mostrar el error
        header("Location: delete.php?id=" . $id_conductor);
        exit;
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Eliminar Conductor</h1>
        <div>
            <a href="view.php?id=<?php echo $id_conductor; ?>" class="btn btn-info me-2">
                <i class="bi bi-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
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
    
    <div class="card border-danger mb-4">
        <div class="card-header bg-danger text-white">
            <h3 class="card-title mb-0">Confirmar Eliminación</h3>
        </div>
        <div class="card-body">
            <h4 class="mb-3">¿Está seguro que desea eliminar al conductor <strong><?php echo htmlspecialchars($conductor['Nombre']); ?></strong>?</h4>
            
            <?php if ($tieneLicencias): ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> ¡Atención! Este conductor tiene licencias asociadas</h5>
                    <p>Para eliminar este conductor, también debe eliminar sus licencias. Esto eliminará permanentemente toda la información de licencias asociada a este conductor.</p>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-danger">
                <p><i class="bi bi-exclamation-triangle-fill"></i> Esta acción no se puede deshacer. Se eliminarán todos los datos asociados a este conductor, incluyendo:</p>
                <ul>
                    <li>Información personal y de contacto</li>
                    <li>Imágenes de perfil y firma</li>
                    <?php if ($tieneLicencias): ?>
                        <li>Licencias asociadas (si marca la casilla correspondiente)</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            Datos del conductor
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 150px;">Nombre:</th>
                                    <td><?php echo htmlspecialchars($conductor['Nombre']); ?></td>
                                </tr>
                                <tr>
                                    <th>CURP:</th>
                                    <td><?php echo htmlspecialchars($conductor['CURP']); ?></td>
                                </tr>
                                <tr>
                                    <th>RFC:</th>
                                    <td><?php echo htmlspecialchars($conductor['RFC']); ?></td>
                                </tr>
                                <tr>
                                    <th>Teléfono:</th>
                                    <td><?php echo htmlspecialchars($conductor['Telefono']); ?></td>
                                </tr>
                                <tr>
                                    <th>Correo:</th>
                                    <td><?php echo htmlspecialchars($conductor['CorreoElectronico']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            Imágenes
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <div class="row">
                                <div class="col-6 text-center">
                                    <p><strong>Foto de perfil</strong></p>
                                    <?php if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])): ?>
                                        <img src="../uploads/fotos/<?php echo $conductor['ImagenPerfil']; ?>" alt="Foto de perfil" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                    <?php else: ?>
                                        <p class="text-muted"><i>Sin imagen</i></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 text-center">
                                    <p><strong>Firma</strong></p>
                                    <?php if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])): ?>
                                        <img src="../uploads/firmas/<?php echo $conductor['Firma']; ?>" alt="Firma" class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
                                    <?php else: ?>
                                        <p class="text-muted"><i>Sin firma</i></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" onsubmit="return confirmarEliminacion();">
                <?php if ($tieneLicencias): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="eliminar_licencias" name="eliminar_licencias" value="1" required>
                        <label class="form-check-label" for="eliminar_licencias">
                            Confirmo que deseo eliminar también todas las licencias asociadas a este conductor
                        </label>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-end">
                    <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" name="confirmar_eliminar" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Eliminar Permanentemente
                    </button>
                </div>
            </form>
            
            <script>
                function confirmarEliminacion() {
                    return confirm('¿Está completamente seguro que desea eliminar a este conductor? Esta acción no se puede deshacer.');
                }
            </script>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>