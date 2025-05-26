<?php
require_once '../includes/db_connection.php';
require_once '../includes/session.php';

// Si ya está autenticado, redirigir
if (is_authenticated()) {
    header("Location: /index.php");
    exit;
}

$error = null;

/**
 * Genera una API key única
 */
function generateAPIKey($pdo) {
    do {
        $bytes = random_bytes(32);
        $apiKey = bin2hex($bytes);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE api_key = ?");
        $stmt->execute([$apiKey]);
        $exists = (bool)$stmt->fetchColumn();
    } while ($exists);
    
    return $apiKey;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son obligatorios";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE Username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = "Usuario no encontrado";
            } else {
                // Verificar estado de la cuenta
                if ((int)$user['Bloquqo'] === 1) {
                    $error = "Cuenta bloqueada. Contacta al administrador.";
                } 
                else if ((int)$user['Status'] === 0) {
                    $error = "Cuenta desactivada. Contacta al administrador.";
                } 
                // Verificar contraseña
                else if ($user['Password'] == $password) {
                    // Resetear intentos
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET Intentos = 0 WHERE Username = ?");
                    $updateStmt->execute([$username]);
                    
                    // Generar API key
                    $apiKey = generateAPIKey($pdo);
                    $expiryDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Guardar API key
                    $keyStmt = $pdo->prepare("UPDATE usuarios SET api_key = ?, key_expiry = ? WHERE Username = ?");
                    $keyStmt->execute([$apiKey, $expiryDate, $username]);
                    
                    // Establecer variables de sesión
                    $_SESSION['Username'] = $user['Username'];
                    $_SESSION['user_type'] = $user['Tipo'];
                    $_SESSION['user_status'] = $user['Status'];
                    $_SESSION['user_blocked'] = $user['Bloquqo'];
                    $_SESSION['api_key'] = $apiKey;
                    
                    // Redirigir
                    header("Location: /index.php");
                    exit;
                } else {
                    // Incrementar intentos
                    $newAttempts = (int)$user['Intentos'] + 1;
                    $blockAccount = $newAttempts >= 3;
                    
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET Intentos = ?, Bloquqo = ? WHERE Username = ?");
                    $updateStmt->execute([$newAttempts, $blockAccount ? 1 : $user['Bloquqo'], $username]);
                    
                    if ($blockAccount) {
                        $error = "Demasiados intentos fallidos. Cuenta bloqueada.";
                    } else {
                        $error = "Contraseña incorrecta. Intento " . $newAttempts . " de 3.";
                    }
                }
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
    <title>Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: 100px auto; }
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