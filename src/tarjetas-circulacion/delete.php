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
        $stmt = $pdo->prepare("SELECT * FROM tarjetascirculacion WHERE ID_Tarjeta_Circulacion = ?");
        $stmt->execute([$id]);
        $tarjeta = $stmt->fetch();
        
        if (!$tarjeta) {
            $_SESSION['error'] = "La tarjeta de circulación no existe.";
            header("Location: index.php");
            exit;
        }
        
        // Verificar si hay registros dependientes antes de eliminar
        // Ejemplo: verificar si hay pagos asociados a esta tarjeta
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagos WHERE ID_Tarjeta_Circulacion = ?");
        $stmt->execute([$id]);
        $pagoCount = $stmt->fetchColumn();
        
        if ($pagoCount > 0) {
            $_SESSION['error'] = "No se puede eliminar la tarjeta porque tiene pagos asociados.";
            header("Location: view.php?id=" . $id);
            exit;
        }
        
        // Verificar si hay verificaciones asociadas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tarjetasverificacion WHERE ID_Tarjeta_Circulacion = ?");
        $stmt->execute([$id]);
        $verificacionCount = $stmt->fetchColumn();
        
        if ($verificacionCount > 0) {
            $_SESSION['error'] = "No se puede eliminar la tarjeta porque tiene verificaciones asociadas.";
            header("Location: view.php?id=" . $id);
            exit;
        }
        
        // Eliminar la tarjeta
        $stmt = $pdo->prepare("DELETE FROM tarjetascirculacion WHERE ID_Tarjeta_Circulacion = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $_SESSION['success'] = "Tarjeta de circulación eliminada correctamente.";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "Error al eliminar la tarjeta de circulación.";
            header("Location: view.php?id=" . $id);
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header("Location: view.php?id=" . $id);
        exit;
    }
} else {
    // Cargar información de la tarjeta para mostrarla en la página de confirmación
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, v.Marca, v.Modelo, v.AnoFabricacion as Year, p.Nombre as PropietarioNombre 
            FROM tarjetascirculacion tc
            LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
            LEFT JOIN propietarios p ON tc.ID_Propietario = p.ID_Propietario
            WHERE tc.ID_Tarjeta_Circulacion = ?
        ");
        $stmt->execute([$id]);
        $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tarjeta) {
            $_SESSION['error'] = "La tarjeta de circulación no existe.";
            header("Location: index.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al cargar los datos de la tarjeta: " . $e->getMessage();
        header("Location: index.php");
        exit;
    }
}

// Incluir el header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Eliminar Tarjeta de Circulación</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Confirmar eliminación</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        ¿Está seguro que desea eliminar permanentemente esta tarjeta de circulación?
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Información de la tarjeta</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Placas:</strong>
                                    <span><?php echo htmlspecialchars($tarjeta['Placas']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Vehículo:</strong>
                                    <span><?php echo htmlspecialchars($tarjeta['Marca'] . ' ' . $tarjeta['Modelo'] . ' ' . $tarjeta['Year']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Propietario:</strong>
                                    <span><?php echo htmlspecialchars($tarjeta['PropietarioNombre']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Tipo de Servicio:</strong>
                                    <span><?php echo htmlspecialchars($tarjeta['TipoServicio']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Expedición:</strong>
                                    <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['FechaExpedicion']))); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <strong>Vencimiento:</strong>
                                    <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['FechaVencimiento']))); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger">
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer. Todos los datos asociados a esta tarjeta serán eliminados permanentemente.
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