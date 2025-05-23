<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (function_exists('redirect_if_not_authenticated')) {
    redirect_if_not_authenticated('/auth/login.php');
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Manejar la eliminación de tarjetas de circulación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tarjetascirculacion WHERE ID_Tarjeta_Circulacion = ?");
        $stmt->execute([$id]);
        $tarjeta = $stmt->fetch();
        
        if (!$tarjeta) {
            $_SESSION['error'] = "La tarjeta de circulación no existe.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM tarjetascirculacion WHERE ID_Tarjeta_Circulacion = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Tarjeta de circulación eliminada correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar la tarjeta de circulación.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tarjetascirculacion");
    $totalRegistros = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar registros: " . $e->getMessage();
    $totalRegistros = 0;
}

$registrosPorPagina = 10;
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$paginaActual = isset($_GET['pagina']) ? max(1, min($totalPaginas, (int) $_GET['pagina'])) : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Obtener registros para tarjetas de circulación
try {
    $query = "
        SELECT tc.*, v.Marca, v.Modelo, v.AnoFabricacion, v.Color, p.Nombre as NombrePropietario
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios p ON tc.ID_Propietario = p.ID_Propietario
        ORDER BY tc.FechaExpedicion DESC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar las tarjetas de circulación: " . $e->getMessage();
    $tarjetas = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Tarjetas de Circulación</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Tarjeta
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

    <?php if (empty($tarjetas)): ?>
        <div class="alert alert-info">
            No se encontraron tarjetas de circulación. <a href="create.php">Registrar una nueva tarjeta de circulación</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Placas</th>
                        <th>Vehículo</th>
                        <th>Propietario</th>
                        <th>Fecha Expedición</th>
                        <th>Fecha Vencimiento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tarjetas as $tarjeta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tarjeta['Placas']); ?></td>
                            <td>
                                <?php 
                                    $vehiculo = '';
                                    if (!empty($tarjeta['Marca']) && !empty($tarjeta['Modelo'])) {
                                        $vehiculo = $tarjeta['Marca'] . ' ' . $tarjeta['Modelo'];
                                        if (!empty($tarjeta['AnoFabricacion'])) {
                                            $vehiculo .= ' (' . $tarjeta['AnoFabricacion'] . ')';
                                        }
                                    } else {
                                        $vehiculo = 'No disponible';
                                    }
                                    echo htmlspecialchars($vehiculo);
                                ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($tarjeta['NombrePropietario'] ?? 'No especificado'); ?>
                            </td>
                            <td><?php echo !empty($tarjeta['FechaExpedicion']) ? date('d/m/Y', strtotime($tarjeta['FechaExpedicion'])) : 'No disponible'; ?></td>
                            <td><?php echo !empty($tarjeta['FechaVencimiento']) ? date('d/m/Y', strtotime($tarjeta['FechaVencimiento'])) : 'No disponible'; ?></td>
                            <td>
                                <?php 
                                    $hoy = date('Y-m-d');
                                    $vencimiento = $tarjeta['FechaVencimiento'] ?? '';
                                    $badgeClass = 'secondary';
                                    $estadoText = 'No disponible';
                                    
                                    if (!empty($vencimiento)) {
                                        if ($hoy > $vencimiento) {
                                            $badgeClass = 'danger';
                                            $estadoText = 'Vencida';
                                        } else {
                                            $diasRestantes = (strtotime($vencimiento) - strtotime($hoy)) / (60 * 60 * 24);
                                            if ($diasRestantes <= 30) {
                                                $badgeClass = 'warning';
                                                $estadoText = 'Por vencer';
                                            } else {
                                                $badgeClass = 'success';
                                                $estadoText = 'Vigente';
                                            }
                                        }
                                    }
                                ?>
                                <span class="badge bg-<?php echo $badgeClass; ?>">
                                    <?php echo $estadoText; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <div class="modal fade" id="deleteModal<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar la tarjeta de circulación con placas <strong><?php echo htmlspecialchars($tarjeta['Placas']); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $tarjeta['ID_Tarjeta_Circulacion']; ?>">
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

        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación de tarjetas de circulación">
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