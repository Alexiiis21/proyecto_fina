<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación usando el sistema de API keys
redirect_if_not_authenticated('/auth/login.php');

// Definir variables para búsqueda y paginación
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registrosPorPagina = 10;
$inicio = ($pagina - 1) * $registrosPorPagina;

// Construir la consulta SQL base
$sqlBase = "SELECT * FROM vehiculos";
$sqlCount = "SELECT COUNT(*) FROM vehiculos";
$params = [];

// Aplicar filtros de búsqueda si existen
if (!empty($busqueda)) {
    $sqlBase .= " WHERE NumeroSerie LIKE ? OR Placas LIKE ? OR Marca LIKE ? OR Modelo LIKE ?";
    $sqlCount .= " WHERE NumeroSerie LIKE ? OR Placas LIKE ? OR Marca LIKE ? OR Modelo LIKE ?";
    $busquedaParam = "%$busqueda%";
    $params = [$busquedaParam, $busquedaParam, $busquedaParam, $busquedaParam];
}

// Ejecutar consulta paginada
$sqlBase .= " ORDER BY ID_Vehiculo DESC LIMIT $inicio, $registrosPorPagina";

try {
    // Obtener total de registros para paginación
    $stmtCount = $pdo->prepare($sqlCount);
    if (!empty($params)) {
        $stmtCount->execute($params);
    } else {
        $stmtCount->execute();
    }
    $totalRegistros = $stmtCount->fetchColumn();
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
    
    // Obtener los registros para esta página
    $stmt = $pdo->prepare($sqlBase);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Manejar error
    $_SESSION['error'] = "Error al obtener los vehículos: " . $e->getMessage();
    $vehiculos = [];
    $totalPaginas = 0;
}

// Cargar la plantilla del encabezado
require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestión de Vehículos</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Vehículo
        </a>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
                echo htmlspecialchars($_SESSION['error']); 
                unset($_SESSION['error']); 
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (count($vehiculos) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Placas</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Año</th>
                        <th>Color</th>
                        <th>Tipo Combustible</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehiculos as $vehiculo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vehiculo['ID_Vehiculo']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['Placas']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['Marca']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['Modelo']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['AnoFabricacion']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['Color']); ?></td>
                            <td><?php echo htmlspecialchars($vehiculo['TipoCombustible']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?php echo $vehiculo['ID_Vehiculo']; ?>" class="btn btn-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger delete-btn" 
                                            data-id="<?php echo $vehiculo['ID_Vehiculo']; ?>" 
                                            data-descripcion="<?php echo htmlspecialchars($vehiculo['Marca'] . ' ' . $vehiculo['Modelo'] . ' (' . $vehiculo['Placas'] . ')'); ?>" 
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Navegación de páginas">
                <ul class="pagination justify-content-center">
                    <?php if ($pagina > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=1<?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $rangoInicio = max(1, $pagina - 2);
                    $rangoFin = min($totalPaginas, $pagina + 2);
                    
                    for ($i = $rangoInicio; $i <= $rangoFin; $i++): 
                    ?>
                        <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?pagina=<?php echo $totalPaginas; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="alert alert-info">
            <?php if (empty($busqueda)): ?>
                No hay vehículos registrados. <a href="create.php" class="alert-link">Registre un nuevo vehículo</a>.
            <?php else: ?>
                No se encontraron vehículos que coincidan con la búsqueda. <a href="index.php" class="alert-link">Mostrar todos</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar el vehículo: <strong id="vehiculo-nombre"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteForm" action="delete.php" method="POST">
                    <input type="hidden" id="delete-id" name="id">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar modal de eliminación
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const deleteForm = document.getElementById('deleteForm');
    const deleteIdInput = document.getElementById('delete-id');
    const vehiculoNombre = document.getElementById('vehiculo-nombre');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const descripcion = this.getAttribute('data-descripcion');
            
            deleteIdInput.value = id;
            vehiculoNombre.textContent = descripcion;
            deleteModal.show();
        });
    });
    
    // Manejar la eliminación vía AJAX
    deleteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(new FormData(deleteForm))
        })
        .then(response => response.json())
        .then(data => {
            deleteModal.hide();
            
            if (data.success) {
                // Crear alerta de éxito
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success alert-dismissible fade show';
                successAlert.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insertar alerta al principio del contenedor
                const container = document.querySelector('.container');
                container.insertBefore(successAlert, container.firstChild.nextSibling);
                
                // Eliminar la fila de la tabla
                const vehiculoId = deleteIdInput.value;
                const filaAEliminar = document.querySelector(`button[data-id="${vehiculoId}"]`).closest('tr');
                filaAEliminar.remove();
                
                // Recargar la página si no quedan vehículos
                const filas = document.querySelectorAll('tbody tr');
                if (filas.length === 0) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                // Crear alerta de error
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger alert-dismissible fade show';
                errorAlert.innerHTML = `
                    ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insertar alerta al principio del contenedor
                const container = document.querySelector('.container');
                container.insertBefore(errorAlert, container.firstChild.nextSibling);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            deleteModal.hide();
            
            // Crear alerta de error
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger alert-dismissible fade show';
            errorAlert.innerHTML = `
                Error al procesar la solicitud. Inténtelo de nuevo.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Insertar alerta al principio del contenedor
            const container = document.querySelector('.container');
            container.insertBefore(errorAlert, container.firstChild.nextSibling);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>