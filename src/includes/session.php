<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connection.php';

/**
 * Verifica si el usuario está autenticado
 */
function is_authenticated() {
    return isset($_SESSION['Username']) && !empty($_SESSION['Username']);
}

/**
 * Verifica si el usuario es administrador
 */
function is_admin() {
    return is_authenticated() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'A';
}

/**
 * Verifica si la cuenta está activa
 */
function is_account_active() {
    return is_authenticated() && isset($_SESSION['user_status']) && $_SESSION['user_status'] == 1;
}

/**
 * Verifica si la cuenta está bloqueada
 */
function is_account_blocked() {
    return is_authenticated() && isset($_SESSION['user_blocked']) && $_SESSION['user_blocked'] == 1;
}

/**
 * Verifica si la API key es válida
 */
function is_api_key_valid() {
    if (!is_authenticated() || !isset($_SESSION['api_key'])) {
        return false;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM usuarios 
            WHERE Username = ? 
            AND api_key = ? 
            AND (key_expiry IS NULL OR key_expiry > NOW())
        ");
        $stmt->execute([$_SESSION['Username'], $_SESSION['api_key']]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al verificar API key: " . $e->getMessage());
        return false;
    }
}

/**
 * Redirige si no está autenticado o API key no válida
 */
function redirect_if_not_authenticated($redirect_url = '/auth/login.php') {
    if (!is_authenticated()) {
        header("Location: $redirect_url");
        exit;
    }
    
    if (!is_api_key_valid()) {
        session_unset();
        session_destroy();
        header("Location: $redirect_url");
        exit;
    }
    
    if (is_account_blocked()) {
        $_SESSION['error'] = "Tu cuenta ha sido bloqueada.";
        session_unset();
        session_destroy();
        header("Location: $redirect_url");
        exit;
    }
    
    if (!is_account_active()) {
        $_SESSION['error'] = "Tu cuenta está desactivada.";
        session_unset();
        session_destroy();
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Redirige si no es administrador
 */
function redirect_if_not_admin($redirect_url = '/index.php') {
    redirect_if_not_authenticated('/auth/login.php');
    
    if (!is_admin()) {
        $_SESSION['error'] = "Acceso denegado.";
        header("Location: $redirect_url");
        exit;
    }
}
?>