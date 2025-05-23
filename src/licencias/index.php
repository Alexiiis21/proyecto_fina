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

// Manejar la eliminación de licencias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        // Verificar si la licencia existe
        $stmt = $pdo->prepare("SELECT * FROM licencias WHERE ID_Licencia = ?");
        $stmt->execute([$id]);
        $licencia = $stmt->fetch();
        
        if (!$licencia) {
            $_SESSION['error'] = "La licencia no existe.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM licencias WHERE ID_Licencia = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Licencia eliminada correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar la licencia.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM licencias");
    $totalRegistros = $stmt->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al contar registros: " . $e->getMessage();
    $totalRegistros = 0;
}

$registrosPorPagina = 10;
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$paginaActual = isset($_GET['pagina']) ? max(1, min($totalPaginas, (int) $_GET['pagina'])) : 1;
$inicio = ($paginaActual - 1) * $registrosPorPagina;

// Obtener registros para la página actual con JOIN a la tabla conductores
try {
    $query = "
        SELECT l.*, c.nombre as NombreConductor
        FROM licencias l
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        ORDER BY l.FechaExpedicion DESC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $licencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar las licencias: " . $e->getMessage();
    $licencias = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Licencias de Conducir</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Licencia
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

    <?php if (empty($licencias)): ?>
        <div class="alert alert-info">
            No se encontraron licencias de conducir. <a href="create.php">Registrar una nueva licencia</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Número de Licencia</th>
                        <th>Titular</th>
                        <th>Tipo</th>
                        <th>Fecha Expedición</th>
                        <th>Fecha Vencimiento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licencias as $licencia): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($licencia['ID_Licencia']); ?></td>
                            <td><?php echo htmlspecialchars($licencia['NumeroLicencia'] ?? $licencia['Numero'] ?? $licencia['Folio'] ?? 'No disponible'); ?></td>
                            <td>
                                <?php 
                                    // Usar el nombre del conductor obtenido del JOIN
                                    echo htmlspecialchars($licencia['NombreConductor'] ?? 'No disponible');
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $tipoLicencia = '';
                                    $tipoField = $licencia['TipoLicencia'] ?? $licencia['Tipo'] ?? '';
                                    
                                    switch($tipoField) {
                                        case 'A':
                                            $tipoLicencia = 'Tipo A (Automovilista)';
                                            break;
                                        case 'B':
                                            $tipoLicencia = 'Tipo B (Chofer)';
                                            break;
                                        case 'C':
                                            $tipoLicencia = 'Tipo C (Motociclista)';
                                            break;
                                        case 'D':
                                            $tipoLicencia = 'Tipo D (Transporte Público)';
                                            break;
                                        default:
                                            $tipoLicencia = $tipoField ?: 'No especificado';
                                    }
                                    echo htmlspecialchars($tipoLicencia);
                                ?>
                            </td>
                            <td>
                                <?php 
                                    // Buscar la fecha de expedición en diferentes campos posibles
                                    $fechaExpedicion = '';
                                    
                                    if (!empty($licencia['FechaExpedicion'])) {
                                        $fechaExpedicion = $licencia['FechaExpedicion'];
                                    } elseif (!empty($licencia['FechaEmision'])) {
                                        $fechaExpedicion = $licencia['FechaEmision'];
                                    } elseif (!empty($licencia['Expedicion'])) {
                                        $fechaExpedicion = $licencia['Expedicion'];
                                    }
                                    
                                    echo !empty($fechaExpedicion) ? date('d/m/Y', strtotime($fechaExpedicion)) : 'No disponible';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    // Buscar la fecha de vencimiento en diferentes campos posibles
                                    $fechaVencimiento = '';
                                    
                                    if (!empty($licencia['FechaVencimiento'])) {
                                        $fechaVencimiento = $licencia['FechaVencimiento'];
                                    } elseif (!empty($licencia['Vigencia'])) {
                                        $fechaVencimiento = $licencia['Vigencia'];
                                    } elseif (!empty($licencia['FechaExpiracion'])) {
                                        $fechaVencimiento = $licencia['FechaExpiracion'];
                                    }
                                    
                                    echo !empty($fechaVencimiento) ? date('d/m/Y', strtotime($fechaVencimiento)) : 'No disponible';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $hoy = date('Y-m-d');
                                    $vencimiento = '';
                                    
                                    if (!empty($licencia['FechaVencimiento'])) {
                                        $vencimiento = $licencia['FechaVencimiento'];
                                    } elseif (!empty($licencia['Vigencia'])) {
                                        $vencimiento = $licencia['Vigencia'];
                                    } elseif (!empty($licencia['FechaExpiracion'])) {
                                        $vencimiento = $licencia['FechaExpiracion'];
                                    }
                                    
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
                                    <a href="view.php?id=<?php echo $licencia['ID_Licencia']; ?>" class="btn btn-info" title="Ver detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $licencia['ID_Licencia']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $licencia['ID_Licencia']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Modal de confirmación de eliminación -->
                                <div class="modal fade" id="deleteModal<?php echo $licencia['ID_Licencia']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $licencia['ID_Licencia']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $licencia['ID_Licencia']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar la licencia con número <strong><?php echo htmlspecialchars($licencia['NumeroLicencia'] ?? $licencia['Numero'] ?? $licencia['Folio'] ?? 'No disponible'); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $licencia['ID_Licencia']; ?>">
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
            <nav aria-label="Paginación de licencias">
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