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

// Manejar la eliminación de pagos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        // Verificar si el pago existe
        $stmt = $pdo->prepare("SELECT * FROM pagos WHERE ID_Pago = ?");
        $stmt->execute([$id]);
        $pago = $stmt->fetch();
        
        if (!$pago) {
            $_SESSION['error'] = "El pago no existe.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM pagos WHERE ID_Pago = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Pago eliminado correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar el pago.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pagos");
    $totalRegistros = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar registros: " . $e->getMessage();
    $totalRegistros = 0;
}

$registrosPorPagina = 10;
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$paginaActual = isset($_GET['pagina']) ? max(1, min($totalPaginas, (int) $_GET['pagina'])) : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Obtener registros para la página actual
try {
    $query = "
        SELECT p.*, tc.Placas, v.Marca, v.Modelo, v.AnoFabricacion
        FROM pagos p
        LEFT JOIN tarjetascirculacion tc ON p.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        ORDER BY p.FechaPago DESC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los pagos: " . $e->getMessage();
    $pagos = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Pagos</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Pago
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pagos)): ?>
        <div class="alert alert-info">
            No se encontraron pagos. <a href="create.php">Registrar un nuevo pago</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Número de Transacción</th>
                        <th>Tarjeta de Circulación</th>
                        <th>Vehículo</th>
                        <th>Importe</th>
                        <th>Fecha de Pago</th>
                        <th>Método de Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pago['ID_Pago']); ?></td>
                            <td><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></td>
                            <td><?php echo htmlspecialchars($pago['Placas'] ?? 'No disponible'); ?></td>
                            <td>
                                <?php 
                                    $vehiculo = '';
                                    if (!empty($pago['Marca']) && !empty($pago['Modelo'])) {
                                        $vehiculo = $pago['Marca'] . ' ' . $pago['Modelo'];
                                        if (!empty($pago['AnoFabricacion'])) {
                                            $vehiculo .= ' (' . $pago['AnoFabricacion'] . ')';
                                        }
                                    } else {
                                        $vehiculo = 'No disponible';
                                    }
                                    echo htmlspecialchars($vehiculo);
                                ?>
                            </td>
                            <td>$<?php echo number_format($pago['Importe'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pago['FechaPago'])); ?></td>
                            <td>
                                <?php 
                                    $badgeClass = 'secondary';
                                    switch ($pago['MetodoPago']) {
                                        case 'EFECTIVO':
                                            $badgeClass = 'success';
                                            break;
                                        case 'TARJETA':
                                            $badgeClass = 'primary';
                                            break;
                                        case 'TRANSFERENCIA':
                                            $badgeClass = 'info';
                                            break;
                                    }
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($pago['MetodoPago'] ?? 'No especificado'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $pago['ID_Pago']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $pago['ID_Pago']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $pago['ID_Pago']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Modal de confirmación de eliminación -->
                                <div class="modal fade" id="deleteModal<?php echo $pago['ID_Pago']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $pago['ID_Pago']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $pago['ID_Pago']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar el pago con número de transacción <strong><?php echo htmlspecialchars($pago['NumeroTransaccion']); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $pago['ID_Pago']; ?>">
                                                    <button type="submit" name="delete" class="btn btn-danger">Eliminar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación de pagos">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($paginaActual <= 1) ? '#' : '?pagina=' . ($paginaActual - 1); ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = max(1, $paginaActual - 2); $i <= min($paginaActual + 2, $totalPaginas); $i++): ?>
                        <li class="page-item <?php echo ($i == $paginaActual) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo ($paginaActual >= $totalPaginas) ? '#' : '?pagina=' . ($paginaActual + 1); ?>" aria-label="Siguiente">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>