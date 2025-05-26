<?php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';
require_once '../auth/admin_check.php'; 

try {
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY Username");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al obtener usuarios: " . $e->getMessage();
}

$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = $_POST['username'] ?? '';
    
    if ($username && $action) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE usuarios SET Status = 1 WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Cuenta activada correctamente";
                    break;
                
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE usuarios SET Status = 0 WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Cuenta desactivada correctamente";
                    break;
                
                case 'unblock':
                    $stmt = $pdo->prepare("UPDATE usuarios SET Bloquqo = 0, Intentos = 0 WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Cuenta desbloqueada correctamente";
                    break;
                
                case 'block':
                    $stmt = $pdo->prepare("UPDATE usuarios SET Bloquqo = 1 WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Cuenta bloqueada correctamente";
                    break;
                
                case 'reset':
                    $stmt = $pdo->prepare("UPDATE usuarios SET Intentos = 0 WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Intentos reiniciados correctamente";
                    break;
                
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE Username = ?");
                    $stmt->execute([$username]);
                    $success = "Usuario eliminado correctamente";
                    break;
            }
            
            // Actualizar lista de usuarios después de la acción
            $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY Username");
            $usuarios = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Error al procesar la acción: " . $e->getMessage();
        }
    }
}

// Procesar nuevo usuario
if (isset($_POST['new_username'])) {
    $new_username = cleanInput($_POST['new_username']);
    $new_password = cleanInput($_POST['new_password']);
    $new_type = cleanInput($_POST['new_type']);
    
    if (empty($new_username) || empty($new_password)) {
        $error = "Usuario y contraseña son obligatorios";
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE Username = ?");
            $stmt->execute([$new_username]);
            $userExists = (int)$stmt->fetchColumn();
            
            if ($userExists) {
                $error = "El usuario ya existe";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (Username, Password, Tipo, Status, Bloquqo, Intentos) 
                    VALUES (?, ?, ?, 1, 0, 0)
                ");
                $stmt->execute([$new_username, $new_password, $new_type]);
                
                $success = "Usuario creado correctamente";
                
                // Actualizar lista de usuarios
                $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY Username");
                $usuarios = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $error = "Error al crear usuario: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestión de Usuarios</h1>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newUserModal">
            <i class="bi bi-person-plus"></i> Nuevo Usuario
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Bloqueado</th>
                            <th>Intentos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No hay usuarios registrados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['Username']); ?></td>
                                    <td>
                                        <?php if ($usuario['Tipo'] == 'A'): ?>
                                            <span class="badge bg-danger">Administrador</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Usuario</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['Status'] == 1): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['Bloquqo'] == 1): ?>
                                            <span class="badge bg-danger">Bloqueado</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Desbloqueado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $usuario['Intentos']; ?>
                                        <?php if ($usuario['Intentos'] > 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de reiniciar los intentos?')">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                <input type="hidden" name="action" value="reset">
                                                <button type="submit" class="btn btn-sm btn-warning">Reiniciar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($usuario['Status'] == 1): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de desactivar este usuario?')">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn btn-sm btn-warning">Desactivar</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-sm btn-success">Activar</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($usuario['Bloquqo'] == 1): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                    <input type="hidden" name="action" value="unblock">
                                                    <button type="submit" class="btn btn-sm btn-success">Desbloquear</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de bloquear este usuario?')">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                    <input type="hidden" name="action" value="block">
                                                    <button type="submit" class="btn btn-sm btn-danger">Bloquear</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($usuario['Username']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newUserModal" tabindex="-1" aria-labelledby="newUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newUserModalLabel">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_username" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="new_username" name="new_username" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_type" class="form-label">Tipo de Usuario</label>
                        <select class="form-select" id="new_type" name="new_type" required>
                            <option value="U">Usuario</option>
                            <option value="A">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>