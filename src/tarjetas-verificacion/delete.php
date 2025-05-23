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
    $_SESSION['error'] = "ID de tarjeta no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Verificar si viene confirmación para eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Verificar si la tarjeta existe antes de eliminar
        $stmt = $pdo->prepare("SELECT * FROM tarjetasverificacion WHERE ID_Tarjeta_Verificacion = ?");
        $stmt->execute([$id]);
        $tarjeta = $stmt->fetch();
        
        if (!$tarjeta) {
            $_SESSION['error'] = "La tarjeta de verificación no existe.";
            header("Location: index.php");
            exit;
        }
        
        // Eliminar la tarjeta de verificación
        $stmt = $pdo->prepare("DELETE FROM tarjetasverificacion WHERE ID_Tarjeta_Verificacion = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $_SESSION['success'] = "Tarjeta de verificación eliminada correctamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar la tarjeta de verificación.";
        }
        
        header("Location: index.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Obtener datos de la tarjeta para mostrar en la página de confirmación
try {
    $stmt = $pdo->prepare("
        SELECT tv.*, 
               v.Marca, v.Modelo, v.AnoFabricacion, v.Placas,
               cv.Nombre as CentroVerificacion,
               tc.Placas as PlacasCirculacion
        FROM tarjetasverificacion tv
        LEFT JOIN vehiculos v ON tv.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN centrosverificacion cv ON tv.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON tv.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        WHERE tv.ID_Tarjeta_Verificacion = ?
    ");
    $stmt->execute([$id]);
    $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarjeta) {
        $_SESSION['error'] = "La tarjeta de verificación no existe.";
        header("Location: index.php");
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar la tarjeta: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Eliminar Tarjeta de Verificación</h5>
                </div>
                <div class="card-body">
                    <h4 class="mb-3">¿Está seguro que desea eliminar esta tarjeta de verificación?</h4>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Folio:</strong> <?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?></p>
                            <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($tarjeta['Marca'] . ' ' . $tarjeta['Modelo'] . ' (' . $tarjeta['AnoFabricacion'] . ')'); ?></p>
                            <p><strong>Placas:</strong> <?php echo htmlspecialchars($tarjeta['Placas']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Centro de verificación:</strong> <?php echo htmlspecialchars($tarjeta['CentroVerificacion']); ?></p>
                            <p><strong>Fecha de expedición:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['FechaExpedicion']))); ?></p>
                            <p><strong>Vigencia:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['Vigencia']))); ?></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger">
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer. Todos los datos asociados a esta tarjeta de verificación serán eliminados permanentemente.
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <form method="POST">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Sí, eliminar tarjeta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>