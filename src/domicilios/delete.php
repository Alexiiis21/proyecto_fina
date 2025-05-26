<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación usando el sistema de API keys
redirect_if_not_authenticated('/auth/login.php');

// Solo permitir solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Verificación adicional para operaciones sensibles
if (!is_api_key_valid()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Por seguridad, debes iniciar sesión nuevamente para realizar esta acción.'
    ]);
    exit;
}

// Verificar que se proporcionó un ID
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID de domicilio no válido.'
    ]);
    exit;
}

$id = (int) $_POST['id'];

// Verificar si el domicilio existe
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM domicilios WHERE ID_Domicilio = ?");
    $stmt->execute([$id]);
    $exists = (bool) $stmt->fetchColumn();
    
    if (!$exists) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'El domicilio no existe.'
        ]);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar el domicilio: ' . $e->getMessage()
    ]);
    exit;
}

// Verificar si el domicilio está en uso en otras tablas
try {
    // Verificar en conductores (ajustar según tu esquema de base de datos)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conductores WHERE ID_Domicilio = ?");
    $stmt->execute([$id]);
    $enUsoConductores = (bool) $stmt->fetchColumn();
    
    // Verificar en otras tablas que puedan tener relación con domicilios
    // Ejemplo con centros de verificación
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM centros_verificacion WHERE ID_Domicilio = ?");
    $stmt->execute([$id]);
    $enUsoCentros = (bool) $stmt->fetchColumn();
    
    if ($enUsoConductores || $enUsoCentros) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar el domicilio porque está en uso en otros registros.'
        ]);
        exit;
    }
} catch (PDOException $e) {
    // Si la tabla no existe o hay otro error, continuamos (la verificación es opcional)
    // Puedes descomentar la siguiente línea para depuración
    // error_log("Error al verificar dependencias: " . $e->getMessage());
}

// Eliminar el domicilio
try {
    $stmt = $pdo->prepare("DELETE FROM domicilios WHERE ID_Domicilio = ?");
    $stmt->execute([$id]);
    
    // Verificar si realmente se eliminó una fila
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Domicilio eliminado correctamente.'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo eliminar el domicilio.'
        ]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el domicilio: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>