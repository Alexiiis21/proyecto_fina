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
        'message' => 'ID de vehículo no válido.'
    ]);
    exit;
}

$id = (int) $_POST['id'];

// Verificar si el vehículo existe
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehiculos WHERE ID_Vehiculo = ?");
    $stmt->execute([$id]);
    $exists = (bool) $stmt->fetchColumn();
    
    if (!$exists) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'El vehículo no existe.'
        ]);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar el vehículo: ' . $e->getMessage()
    ]);
    exit;
}

// Verificar si el vehículo está en uso en otras tablas
try {
    // Verificar en TarjetasCirculacion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tarjetascirculacion WHERE ID_Vehiculo = ?");
    $stmt->execute([$id]);
    $enUsoCirculacion = (bool) $stmt->fetchColumn();
    
    // Verificar en TarjetasVerificacion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tarjetasverificacion WHERE ID_Vehiculo = ?");
    $stmt->execute([$id]);
    $enUsoVerificacion = (bool) $stmt->fetchColumn();
    
    if ($enUsoCirculacion || $enUsoVerificacion) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar el vehículo porque está en uso en otros registros.'
        ]);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar dependencias: ' . $e->getMessage()
    ]);
    exit;
}

// Eliminar el vehículo
try {
    $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE ID_Vehiculo = ?");
    $stmt->execute([$id]);
    
    // Verificar si realmente se eliminó una fila
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Vehículo eliminado correctamente.'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo eliminar el vehículo.'
        ]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar el vehículo: ' . $e->getMessage()
    ]);
}
?>