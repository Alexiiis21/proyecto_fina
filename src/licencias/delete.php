<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

redirect_if_not_admin('/licencias/index.php');

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de licencia no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'SI') {
        try {
            $stmt = $pdo->prepare("
                SELECT l.NumeroLicencia, c.Nombre 
                FROM licencias l
                LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
                WHERE l.ID_Licencia = ?
            ");
            $stmt->execute([$id]);
            $licencia = $stmt->fetch();
            
            if (!$licencia) {
                $_SESSION['error'] = "La licencia no existe.";
                header("Location: index.php");
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM licencias WHERE ID_Licencia = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Licencia #" . htmlspecialchars($licencia['NumeroLicencia']) . " eliminada correctamente.";
                header("Location: index.php");
                exit;
            } else {
                $_SESSION['error'] = "No se pudo eliminar la licencia.";
                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error al eliminar la licencia: " . $e->getMessage();
            header("Location: index.php");
            exit;
        }
    } else {
        $_SESSION['warning'] = "Eliminación cancelada.";
        header("Location: index.php");
        exit;
    }
}

try {
    // Obtener información de la licencia para el mensaje de confirmación
    $stmt = $pdo->prepare("
        SELECT l.*, c.Nombre as NombreConductor 
        FROM licencias l
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        WHERE l.ID_Licencia = ?
    ");
    $stmt->execute([$id]);
    $licencia = $stmt->fetch();
    
    if (!$licencia) {
        $_SESSION['error'] = "La licencia no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al obtener información de la licencia: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirmar eliminación</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Esta acción no se puede deshacer. Se eliminará permanentemente la licencia.
                    </div>
                    
                    <h5>¿Está seguro de que desea eliminar la siguiente licencia?</h5>
                    
                    <table class="table table-bordered mt-3">
                        <tr>
                            <th style="width: 30%">ID Licencia:</th>
                            <td><?php echo $licencia['ID_Licencia']; ?></td>
                        </tr>
                        <tr>
                            <th>Número de Licencia:</th>
                            <td><?php echo htmlspecialchars($licencia['NumeroLicencia']); ?></td>
                        </tr>
                        <tr>
                            <th>Conductor:</th>
                            <td><?php echo htmlspecialchars($licencia['NombreConductor']); ?></td>
                        </tr>
                        <tr>
                            <th>Tipo de Licencia:</th>
                            <td><?php echo htmlspecialchars($licencia['TipoLicencia']); ?></td>
                        </tr>
                        <tr>
                            <th>Fecha de Vigencia:</th>
                            <td><?php echo htmlspecialchars($licencia['Vigencia']); ?></td>
                        </tr>
                    </table>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $id; ?>" class="mt-4">
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <div class="form-check d-inline-block">
                                <input class="form-check-input" type="checkbox" name="confirmar" id="confirmar" value="SI" required>
                                <label class="form-check-label" for="confirmar">
                                    Confirmo que deseo eliminar esta licencia
                                </label>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Eliminar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>