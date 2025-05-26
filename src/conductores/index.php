<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación
redirect_if_not_authenticated('/auth/login.php');


// Manejar la eliminación de conductores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    try {
        // Verificar si el conductor existe y obtener rutas de imágenes
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE ID_Conductor = ?");
        $stmt->execute([$id]);
        $conductor = $stmt->fetch();
        
        if (!$conductor) {
            $_SESSION['error'] = "El conductor no existe.";
        } else {
            // Eliminar imágenes asociadas si existen
            if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])) {
                unlink("../uploads/fotos/" . $conductor['ImagenPerfil']);
            }
            
            if (!empty($conductor['Firma']) && file_exists("../uploads/firmas/" . $conductor['Firma'])) {
                unlink("../uploads/firmas/" . $conductor['Firma']);
            }
            
            // Eliminar registro de la base de datos
            $stmt = $pdo->prepare("DELETE FROM conductores WHERE ID_Conductor = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $_SESSION['success'] = "Conductor eliminado correctamente.";
            } else {
                $_SESSION['error'] = "Error al eliminar el conductor.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM conductores");
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
        SELECT c.*, l.NumeroLicencia 
        FROM conductores c
        LEFT JOIN licencias l ON c.Licencia = l.ID_Licencia
        ORDER BY c.Nombre ASC
        LIMIT :inicio, :registrosPorPagina
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
    $stmt->execute();
    
    $conductores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los conductores: " . $e->getMessage();
    $conductores = [];
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Conductores</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Conductor
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

    <?php if (empty($conductores)): ?>
        <div class="alert alert-info">
            No se encontraron conductores. <a href="create.php">Registrar un nuevo conductor</a>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Foto</th>
                        <th>Nombre</th>
                        <th>CURP</th>
                        <th>RFC</th>
                        <th>Licencia</th>
                        <th>Contacto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conductores as $conductor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conductor['ID_Conductor']); ?></td>
                            <td>
                                <?php if (!empty($conductor['ImagenPerfil']) && file_exists("../uploads/fotos/" . $conductor['ImagenPerfil'])): ?>
                                    <img src="../uploads/fotos/<?php echo $conductor['ImagenPerfil']; ?>" alt="Foto de <?php echo htmlspecialchars($conductor['Nombre']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                <?php else: ?>
                                    <img src="../assets/img/default-profile.png" alt="Sin foto" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($conductor['Nombre']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['CURP']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['RFC']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['NumeroLicencia'] ?? 'No asignada'); ?></td>
                            <td>
                                <?php 
                                    $contacto = [];
                                    if (!empty($conductor['Telefono'])) {
                                        $contacto[] = '<i class="bi bi-telephone"></i> ' . htmlspecialchars($conductor['Telefono']);
                                    }
                                    if (!empty($conductor['CorreoElectronico'])) {
                                        $contacto[] = '<i class="bi bi-envelope"></i> ' . htmlspecialchars($conductor['CorreoElectronico']);
                                    }
                                    echo !empty($contacto) ? implode('<br>', $contacto) : 'No disponible';
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?php echo $conductor['ID_Conductor']; ?>" class="btn btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $conductor['ID_Conductor']; ?>" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                
                                <!-- Modal de confirmación de eliminación -->
                                <div class="modal fade" id="deleteModal<?php echo $conductor['ID_Conductor']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $conductor['ID_Conductor']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $conductor['ID_Conductor']; ?>">Confirmar eliminación</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                ¿Está seguro que desea eliminar al conductor <strong><?php echo htmlspecialchars($conductor['Nombre']); ?></strong>?
                                                <p class="text-danger mt-2">Esta acción no se puede deshacer y eliminará todas las imágenes asociadas.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <form method="POST">
                                                    <input type="hidden" name="id" value="<?php echo $conductor['ID_Conductor']; ?>">
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
            <nav aria-label="Paginación de conductores">
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