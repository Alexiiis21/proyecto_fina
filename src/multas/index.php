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

// Manejar la eliminación de multas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        // Verificar si la multa existe
        $stmt = $pdo->prepare("SELECT * FROM multas WHERE ID_Multa = ?");
        $stmt->execute([$id]);
        $multa = $stmt->fetch();
        
        if (!$multa) {
            $_SESSION['error'] = "La multa no existe.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM multas WHERE ID_Multa = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Multa eliminada correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar la multa.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM multas");
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
        SELECT m.*, 
               l.NumeroLicencia, 
               c.Nombre AS NombreConductor,
               o.Nombre AS NombreOficial, 
               tc.Placas, 
               v.Marca, v.Modelo, v.AnoFabricacion
        FROM multas m
        LEFT JOIN licencias l ON m.ID_Licencia = l.ID_Licencia
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN oficiales o ON m.ID_Oficial = o.ID_Oficial
        LEFT JOIN tarjetascirculacion tc ON m.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        ORDER BY m.Fecha DESC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $multas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar las multas: " . $e->getMessage();
    $multas = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Multas de Tránsito</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Multa
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

    <?php if (empty($multas)): ?>
        <div class="alert alert-info">
            No se encontraron multas. <a href="create.php">Registrar una nueva multa</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Conductor</th>
                        <th>Vehículo</th>
                        <th>Motivo</th>
                        <th>Importe</th>
                        <th>Oficial</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($multas as $multa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($multa['ID_Multa']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($multa['Fecha'])); ?></td>
                            <td>
                                <?php if (!empty($multa['NombreConductor'])): ?>
                                    <?php echo htmlspecialchars($multa['NombreConductor']); ?><br>
                                    <small class="text-muted">Lic: <?php echo htmlspecialchars($multa['NumeroLicencia']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $vehiculo = '';
                                    if (!empty($multa['Marca']) && !empty($multa['Modelo'])) {
                                        $vehiculo = $multa['Marca'] . ' ' . $multa['Modelo'];
                                        if (!empty($multa['AnoFabricacion'])) {
                                            $vehiculo .= ' (' . $multa['AnoFabricacion'] . ')';
                                        }
                                        echo htmlspecialchars($vehiculo);
                                        echo "<br><small class='text-muted'>Placas: " . htmlspecialchars($multa['Placas']) . "</small>";
                                    } else {
                                        echo '<span class="text-muted">No disponible</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    // Limitamos el motivo a 50 caracteres para la tabla
                                    $motivo = $multa['Motivo'];
                                    if (strlen($motivo) > 50) {
                                        echo htmlspecialchars(substr($motivo, 0, 47) . '...');
                                    } else {
                                        echo htmlspecialchars($motivo);
                                    }
                                ?>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-danger">
                                    $<?php echo number_format($multa['Importe'], 2); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($multa['NombreOficial'] ?? 'No especificado'); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $multa['ID_Multa']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $multa['ID_Multa']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $multa['ID_Multa']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Modal de confirmación de eliminación -->
                                <div class="modal fade" id="deleteModal<?php echo $multa['ID_Multa']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $multa['ID_Multa']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $multa['ID_Multa']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar la multa con ID <strong><?php echo htmlspecialchars($multa['ID_Multa']); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $multa['ID_Multa']; ?>">
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
            <nav aria-label="Paginación de multas">
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