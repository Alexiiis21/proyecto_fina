<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar si el usuario está autenticado
function is_authenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function is_admin() {
    return is_authenticated() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'A';
}

// Función para verificar el estado de la cuenta
function is_account_active() {
    return is_authenticated() && isset($_SESSION['user_status']) && $_SESSION['user_status'] == 1;
}

// Función para verificar si la cuenta está bloqueada
function is_account_blocked() {
    return is_authenticated() && isset($_SESSION['user_blocked']) && $_SESSION['user_blocked'] == 1;
}

// Función para redirigir si no está autenticado
function redirect_if_not_authenticated($redirect_url = '/auth/login.php') {
    if (!is_authenticated()) {
        header("Location: $redirect_url");
        exit;
    }
    
    if (is_account_blocked()) {
        $_SESSION['error'] = "Tu cuenta ha sido bloqueada. Contacta al administrador.";
        header("Location: $redirect_url");
        exit;
    }
    
    if (!is_account_active()) {
        $_SESSION['error'] = "Tu cuenta está desactivada. Contacta al administrador.";
        header("Location: $redirect_url");
        exit;
    }
}

// Función para redirigir si no es administrador
function redirect_if_not_admin($redirect_url = '/index.php') {
    redirect_if_not_authenticated('/auth/login.php');
    
    if (!is_admin()) {
        $_SESSION['error'] = "No tienes permiso para acceder a esta página.";
        header("Location: $redirect_url");
        exit;
    }
}
?>