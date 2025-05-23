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

// Manejar la eliminación de tarjetas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        // Verificar si la tarjeta existe
        $stmt = $pdo->prepare("SELECT * FROM tarjetasverificacion WHERE ID_Tarjeta_Verificacion = ?");
        $stmt->execute([$id]);
        $tarjeta = $stmt->fetch();
        
        if (!$tarjeta) {
            $_SESSION['error'] = "La tarjeta de verificación no existe.";
        } else {
            // Eliminar la tarjeta
            $stmt = $pdo->prepare("DELETE FROM tarjetasverificacion WHERE ID_Tarjeta_Verificacion = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Tarjeta de verificación eliminada correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar la tarjeta de verificación.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tarjetasverificacion");
    $totalRegistros = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar registros: " . $e->getMessage();
    $totalRegistros = 0;
}

$registrosPorPagina = 10;
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$paginaActual = isset($_GET['pagina']) ? max(1, min($totalPaginas, (int) $_GET['pagina'])) : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

try {
    $query = "
        SELECT tv.*, 
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.Placas,
               cv.Nombre as CentroVerificacion,
               tc.Placas as PlacasCirculacion
        FROM tarjetasverificacion tv
        LEFT JOIN vehiculos v ON tv.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN centrosverificacion cv ON tv.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON tv.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        ORDER BY tv.FechaExpedicion DESC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar las tarjetas de verificación: " . $e->getMessage();
    $tarjetas = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Tarjetas de Verificación</h1>
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
            No se encontraron tarjetas de verificación. <a href="create.php">Crear una nueva</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Folio</th>
                        <th>Vehículo</th>
                        <th>Placas</th>
                        <th>Centro de Verificación</th>
                        <th>Fecha Expedición</th>
                        <th>Vigencia</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tarjetas as $tarjeta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($tarjeta['Marca'] . ' ' . $tarjeta['Modelo'] . ' ' . $tarjeta['AnoFabricacion']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($tarjeta['Placas']); ?></td>
                            <td><?php echo htmlspecialchars($tarjeta['CentroVerificacion']); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['FechaExpedicion']))); ?></td>
                            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($tarjeta['Vigencia']))); ?></td>
                            <td>
                                <?php
                                $hoy = date('Y-m-d');
                                $vigencia = $tarjeta['Vigencia'];
                                $diasRestantes = (strtotime($vigencia) - strtotime($hoy)) / (60 * 60 * 24);
                                
                                if ($hoy <= $vigencia) {
                                    $badge = ($diasRestantes <= 30) ? 'warning' : 'success';
                                    $estado = ($diasRestantes <= 30) ? 'Por vencer' : 'Vigente';
                                } else {
                                    $badge = 'danger';
                                    $estado = 'Vencida';
                                }
                                
                                echo '<span class="badge bg-' . $badge . '">' . $estado . '</span>';
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <div class="modal fade" id="deleteModal<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar la tarjeta de verificación con folio <strong><?php echo htmlspecialchars($tarjeta['FolioCertificado']); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $tarjeta['ID_Tarjeta_Verificacion']; ?>">
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
            <nav aria-label="Paginación de tarjetas de verificación">
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