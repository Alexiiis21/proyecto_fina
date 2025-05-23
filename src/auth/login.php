<?php
require_once '../includes/db_connection.php';
require_once '../includes/session.php';

if (is_authenticated()) {
    header("Location: /index.php");
    exit;
}

$error = null;

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validar que se proporcionaron credenciales
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son obligatorios";
    } else {
        try {
            // Buscar usuario en la base de datos
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE Username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verificar si la cuenta está bloqueada
                if ($user['Bloquqo'] == 1) {
                    $error = "Tu cuenta ha sido bloqueada. Contacta al administrador.";
                } 
                // Verificar si la cuenta está inactiva
                else if ($user['Status'] == 0) {
                    $error = "Tu cuenta está desactivada. Contacta al administrador.";
                } 
                // Verificar contraseña
                else if ($user['Password'] == $password) {
                    // Login exitoso - resetear intentos
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET Intentos = 0 WHERE Username = ?");
                    $updateStmt->execute([$username]);
                    
                    // Establecer variables de sesión
                    $_SESSION['user_id'] = $user['Username'];
                    $_SESSION['user_type'] = $user['Tipo'];
                    $_SESSION['user_status'] = $user['Status'];
                    $_SESSION['user_blocked'] = $user['Bloquqo'];
                    
                    // Redirigir a la página principal
                    header("Location: /index.php");
                    exit;
                } else {
                    $newAttempts = $user['Intentos'] + 1;
                    $blockAccount = $newAttempts >= 3;
                    
                    // Actualizar intentos y posiblemente bloquear cuenta
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET Intentos = ?, Bloquqo = ? WHERE Username = ?");
                    $updateStmt->execute([$newAttempts, $blockAccount ? 1 : $user['Bloquqo'], $username]);
                    
                    if ($blockAccount) {
                        $error = "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada.";
                    } else {
                        $error = "Contraseña incorrecta. Intento " . $newAttempts . " de 3.";
                    }
                }
            } else {
                $error = "Usuario no encontrado";
            }
        } catch (PDOException $e) {
            $error = "Error en el servidor: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Control Vehicular</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Control Vehicular</h3>
                <p class="mb-0">Iniciar Sesión</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>