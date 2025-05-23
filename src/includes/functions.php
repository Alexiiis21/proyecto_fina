<?php

// Función para limpiar y validar datos de entrada
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


// Función para validar fecha (formato YYYY-MM-DD)
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Función para obtener el nombre de un conductor por ID
function getConductorName($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT Nombre FROM conductores WHERE ID_Conductor = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ? $result['Nombre'] : 'Desconocido';
    } catch (PDOException $e) {
        return 'Error: ' . $e->getMessage();
    }
}

// Función para obtener datos de un domicilio por ID
function getDomicilioData($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM domicilios WHERE ID_Domicilio = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Función para obtener todos los conductores para un select
function getAllConductores($pdo) {
    try {
        $stmt = $pdo->query("SELECT ID_Conductor, Nombre FROM conductores ORDER BY Nombre");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Función para obtener todos los domicilios para un select
function getAllDomicilios($pdo) {
    try {
        $stmt = $pdo->query("SELECT ID_Domicilio, CONCAT(Calle, ' ', NumeroExterior, ', ', Colonia, ', CP ', CodigoPostal) as Direccion FROM domicilios ORDER BY Calle");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>