<?php
// Iniciar buffer de salida
ob_start();

// Incluir archivos necesarios
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
    $_SESSION['error'] = "ID de pago no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Obtener información del pago para mostrar en la confirmación
try {
    $stmt = $pdo->prepare("
        SELECT p.*, tc.Placas 
        FROM pagos p
        LEFT JOIN tarjetascirculacion tc ON p.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        WHERE p.ID_Pago = ?
    ");
    $stmt->execute([$id]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pago) {
        $_SESSION['error'] = "El pago solicitado no existe.";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar la información del pago: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Procesar la eliminación cuando se confirma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_eliminacion'])) {
    try {
        // Eliminar el pago
        $stmt = $pdo->prepare("DELETE FROM pagos WHERE ID_Pago = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $_SESSION['success'] = "Pago eliminado correctamente.";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "No se pudo eliminar el pago.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el pago: " . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="card-title mb-0">Confirmar Eliminación</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php 
                                echo $_SESSION['error']; 
                                unset($_SESSION['error']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>¡Advertencia!</strong> Esta acción no se puede deshacer.
                    </div>
                    
                    <p>¿Está seguro que desea eliminar el siguiente pago?</p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th class="bg-light" style="width: 30%;">Número de Transacción</th>
                                <td><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Importe</th>
                                <td>$<?php echo number_format($pago['Importe'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Fecha de Pago</th>
                                <td><?php echo date('d/m/Y', strtotime($pago['FechaPago'])); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Método de Pago</th>
                                <td><?php echo htmlspecialchars($pago['MetodoPago']); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Tarjeta de Circulación</th>
                                <td><?php echo htmlspecialchars($pago['Placas'] ?? 'No disponible'); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <form method="post" class="mt-4">
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" name="confirmar_eliminacion" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Confirmar Eliminación
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