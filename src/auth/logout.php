<?php
session_start();

require_once '../includes/db_connection.php';

// Capturar información antes de destruir la sesión
$username = $_SESSION['Username'] ?? null;
$api_key = $_SESSION['api_key'] ?? null;

// Invalidar la API key
if ($username && $api_key) {
    try {
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET api_key = NULL, key_expiry = NULL 
            WHERE Username = ? AND api_key = ?
        ");
        $stmt->execute([$username, $api_key]);
    } catch (PDOException $e) {
        error_log("Error al invalidar API key: " . $e->getMessage());
    }
}

// Limpiar sesión
$_SESSION = array();

// Borrar key de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir sesión
session_destroy();

// Redirigir a login
header("Location: /auth/login.php");
exit;