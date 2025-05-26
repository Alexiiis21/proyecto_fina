<?php
ob_start();

require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (function_exists('redirect_if_not_authenticated')) {
    redirect_if_not_authenticated('/auth/login.php', false);
} elseif (!isset($_SESSION['user_id'])) {
    exit("Acceso no autorizado");
}


// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo "<h1>Error de solicitud</h1>";
    echo "<p>ID de licencia no válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM licencias WHERE ID_Licencia = ?
    ");
    $stmt->execute([$id]);
    $licencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licencia) {
        http_response_code(404); 
        echo "<h1>Error: Licencia no encontrada</h1>";
        echo "<p>La licencia solicitada no existe.</p>";
        echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
        exit;
    }
    
    // Ahora obtenemos los datos del conductor si existe
    if (!empty($licencia['ID_Conductor'])) {
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE ID_Conductor = ?");
        $stmt->execute([$licencia['ID_Conductor']]);
        $conductor = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $conductor = [];
    }
    
    // Y los datos del domicilio si existe
    if (!empty($licencia['ID_Domicilio'])) {
        $stmt = $pdo->prepare("SELECT * FROM domicilios WHERE ID_Domicilio = ?");
        $stmt->execute([$licencia['ID_Domicilio']]);
        $domicilio = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $domicilio = [];
    }
    
} catch (PDOException $e) {
    http_response_code(500); 
    echo "<h1>Error de base de datos</h1>";
    echo "<p>Error al cargar los datos de la licencia: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

ob_clean();

// Generar XML
try {
    // Crear el XML
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><licencia></licencia>');
    
    // Datos principales
    $xml->addChild('numero', $licencia['NumeroLicencia'] ?? '');
    $xml->addChild('tipo', $licencia['TipoLicencia'] ?? '');
    $xml->addChild('fechaExpedicion', $licencia['FechaExpedicion'] ?? '');
    $xml->addChild('vigencia', $licencia['Vigencia'] ?? '');
    $xml->addChild('antiguedad', $licencia['Antiguedad'] ?? '0');
    
    // Datos del conductor
    $conductorXml = $xml->addChild('conductor');
    $conductorXml->addChild('nombre', $conductor['Nombre'] ?? 'No especificado');
    
    // Verificar qué columna de apellido existe en la tabla conductores
    if (isset($conductor['Apellidos'])) {
        $conductorXml->addChild('apellidos', $conductor['Apellidos']);
    } elseif (isset($conductor['Apellido'])) {
        $conductorXml->addChild('apellidos', $conductor['Apellido']);
    } elseif (isset($conductor['ApellidoPaterno'])) {
        $apellido = $conductor['ApellidoPaterno'];
        if (isset($conductor['ApellidoMaterno'])) {
            $apellido .= ' ' . $conductor['ApellidoMaterno'];
        }
        $conductorXml->addChild('apellidos', $apellido);
    } else {
        $conductorXml->addChild('apellidos', 'No especificado');
    }
    
    $conductorXml->addChild('curp', $conductor['CURP'] ?? 'No especificado');
    $conductorXml->addChild('fechaNacimiento', $conductor['FechaNacimiento'] ?? $licencia['FechaNacimiento'] ?? '');
    
    // Datos adicionales
    $restricciones = !empty($conductor['Restricciones']) ? $conductor['Restricciones'] : 'Ninguna';
    $conductorXml->addChild('restricciones', $restricciones);
    
    $donador = isset($conductor['DonadorOrganos']) && $conductor['DonadorOrganos'] == 1 ? 'Si' : 'No';
    $conductorXml->addChild('donadorOrganos', $donador);
    
    $grupoSanguineo = !empty($conductor['GrupoSanguineo']) ? $conductor['GrupoSanguineo'] : 
                      (!empty($licencia['GrupoSanguineo']) ? $licencia['GrupoSanguineo'] : 'No especificado');
    $conductorXml->addChild('grupoSanguineo', $grupoSanguineo);
    
    if (!empty($conductor['NumeroEmergencia'])) {
        $conductorXml->addChild('numeroEmergencia', $conductor['NumeroEmergencia']);
    }
    
    // Datos de domicilio
    $domicilioXml = $xml->addChild('domicilio');
    $domicilioXml->addChild('direccion', $domicilio['Direccion'] ?? 'No especificado');
    
    if (!empty($domicilio['NumeroExterior'])) {
        $domicilioXml->addChild('numeroExterior', $domicilio['NumeroExterior']);
    }
    
    if (!empty($domicilio['Colonia'])) {
        $domicilioXml->addChild('colonia', $domicilio['Colonia']);
    }
    
    if (!empty($domicilio['CodigoPostal'])) {
        $domicilioXml->addChild('codigoPostal', $domicilio['CodigoPostal']);
    }
    
    if (!empty($domicilio['Ciudad'])) {
        $domicilioXml->addChild('ciudad', $domicilio['Ciudad']);
    }
    
    if (!empty($domicilio['Estado'])) {
        $domicilioXml->addChild('estado', $domicilio['Estado']);
    }
    
    // Añadir fecha de generación
    $xml->addChild('fechaGeneracion', date('Y-m-d H:i:s'));
    
    // Configurar headers para forzar la descarga
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="Licencia_' . $licencia['NumeroLicencia'] . '.xml"');
    header('Cache-Control: no-cache, private');
    header('Pragma: no-cache');
    
    // Salida del XML
    echo $xml->asXML();
    exit;
    
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo "<h1>Error generando el XML</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}
?>