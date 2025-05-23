<?php
// Iniciar buffer de salida
ob_start();

// Incluir archivos necesarios
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (function_exists('redirect_if_not_authenticated')) {
    redirect_if_not_authenticated('/auth/login.php');
} elseif (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de tarjeta no válido.";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos de la tarjeta
try {
    // Obtener información completa de la tarjeta
    $stmt = $pdo->prepare("
        SELECT tc.*, 
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor,
               v.TipoCarroceria, v.NumeroAsientos, v.Cilindraje, v.TipoCombustible, v.Uso, 
               v.Transmision, v.NumeroPuertas, v.Clase,
               p.Nombre as PropietarioNombre, p.RFC, p.CURP, p.Telefono, p.CorreoElectronico,
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, 
               d.Municipio as DomicilioMunicipio, d.Estado as DomicilioEstado, d.CodigoPostal
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios p ON tc.ID_Propietario = p.ID_Propietario
        LEFT JOIN domicilios d ON p.ID_Domicilio = d.ID_Domicilio
        WHERE tc.ID_Tarjeta_Circulacion = ?
    ");
    $stmt->execute([$id]);
    $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarjeta) {
        $_SESSION['error'] = "La tarjeta de circulación no existe.";
        header("Location: index.php");
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error al cargar los datos de la tarjeta: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Limpiar buffer antes de generar el XML
ob_clean();

try {
    // Crear un nuevo DOM Document
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    
    // Crear el elemento raíz
    $root = $dom->createElement('TarjetaCirculacion');
    $root->setAttribute('ID', $tarjeta['ID_Tarjeta_Circulacion']);
    $root->setAttribute('FechaExpedicion', $tarjeta['FechaExpedicion']);
    $root->setAttribute('FechaVencimiento', $tarjeta['FechaVencimiento']);
    $dom->appendChild($root);
    
    // Crear sección de datos generales de la tarjeta
    $datosGenerales = $dom->createElement('DatosGenerales');
    
    // Agregar elementos a datos generales
    $placas = $dom->createElement('Placas', htmlspecialchars($tarjeta['Placas'] ?? ''));
    $datosGenerales->appendChild($placas);
    
    $tipoServicio = $dom->createElement('TipoServicio', htmlspecialchars($tarjeta['TipoServicio'] ?? ''));
    $datosGenerales->appendChild($tipoServicio);
    
    $municipio = $dom->createElement('Municipio', htmlspecialchars($tarjeta['Municipio'] ?? ''));
    $datosGenerales->appendChild($municipio);
    
    $estado = $dom->createElement('Estado', htmlspecialchars($tarjeta['Estado'] ?? ''));
    $datosGenerales->appendChild($estado);
    
    $localidad = $dom->createElement('Localidad', htmlspecialchars($tarjeta['Localidad'] ?? ''));
    $datosGenerales->appendChild($localidad);
    
    $origen = $dom->createElement('Origen', htmlspecialchars($tarjeta['Origen'] ?? ''));
    $datosGenerales->appendChild($origen);
    
    // Agregar datos generales al root
    $root->appendChild($datosGenerales);
    
    // Crear sección de datos del vehículo
    $datosVehiculo = $dom->createElement('Vehiculo');
    $datosVehiculo->setAttribute('ID', $tarjeta['ID_Vehiculo']);
    
    // Función para añadir elemento si existe el valor
    function addElementIfExists($dom, $parent, $name, $value) {
        if (isset($value) && $value !== '') {
            $element = $dom->createElement($name, htmlspecialchars($value));
            $parent->appendChild($element);
        }
    }
    
    // Agregar elementos a datos del vehículo
    addElementIfExists($dom, $datosVehiculo, 'Marca', $tarjeta['Marca']);
    addElementIfExists($dom, $datosVehiculo, 'Modelo', $tarjeta['Modelo']);
    addElementIfExists($dom, $datosVehiculo, 'AnoFabricacion', $tarjeta['AnoFabricacion']);
    addElementIfExists($dom, $datosVehiculo, 'Color', $tarjeta['Color']);
    addElementIfExists($dom, $datosVehiculo, 'NumeroSerie', $tarjeta['NumeroSerie']);
    addElementIfExists($dom, $datosVehiculo, 'NumeroMotor', $tarjeta['NumeroMotor']);
    addElementIfExists($dom, $datosVehiculo, 'TipoCarroceria', $tarjeta['TipoCarroceria']);
    addElementIfExists($dom, $datosVehiculo, 'NumeroAsientos', $tarjeta['NumeroAsientos']);
    addElementIfExists($dom, $datosVehiculo, 'Cilindraje', $tarjeta['Cilindraje']);
    addElementIfExists($dom, $datosVehiculo, 'TipoCombustible', $tarjeta['TipoCombustible']);
    addElementIfExists($dom, $datosVehiculo, 'Uso', $tarjeta['Uso']);
    addElementIfExists($dom, $datosVehiculo, 'Transmision', $tarjeta['Transmision']);
    addElementIfExists($dom, $datosVehiculo, 'NumeroPuertas', $tarjeta['NumeroPuertas']);
    addElementIfExists($dom, $datosVehiculo, 'Clase', $tarjeta['Clase']);
    
    // Agregar datos del vehículo al root
    $root->appendChild($datosVehiculo);
    
    // Crear sección de datos del propietario
    $datosPropietario = $dom->createElement('Propietario');
    $datosPropietario->setAttribute('ID', $tarjeta['ID_Propietario']);
    
    // Agregar elementos a datos del propietario
    addElementIfExists($dom, $datosPropietario, 'Nombre', $tarjeta['PropietarioNombre']);
    addElementIfExists($dom, $datosPropietario, 'RFC', $tarjeta['RFC']);
    addElementIfExists($dom, $datosPropietario, 'CURP', $tarjeta['CURP']);
    addElementIfExists($dom, $datosPropietario, 'Telefono', $tarjeta['Telefono']);
    addElementIfExists($dom, $datosPropietario, 'CorreoElectronico', $tarjeta['CorreoElectronico']);
    
    // Crear sección de domicilio del propietario
    $domicilio = $dom->createElement('Domicilio');
    
    // Agregar elementos al domicilio
    addElementIfExists($dom, $domicilio, 'Calle', $tarjeta['Calle']);
    addElementIfExists($dom, $domicilio, 'NumeroExterior', $tarjeta['NumeroExterior']);
    addElementIfExists($dom, $domicilio, 'NumeroInterior', $tarjeta['NumeroInterior']);
    addElementIfExists($dom, $domicilio, 'Colonia', $tarjeta['Colonia']);
    addElementIfExists($dom, $domicilio, 'Municipio', $tarjeta['DomicilioMunicipio']);
    addElementIfExists($dom, $domicilio, 'Estado', $tarjeta['DomicilioEstado']);
    addElementIfExists($dom, $domicilio, 'CodigoPostal', $tarjeta['CodigoPostal']);
    
    // Agregar domicilio a datos del propietario si tiene elementos
    if ($domicilio->hasChildNodes()) {
        $datosPropietario->appendChild($domicilio);
    }
    
    // Agregar datos del propietario al root
    $root->appendChild($datosPropietario);
    
    // Crear sección de Características Oficiales (sello, holograma, etc.)
    $caracteristicasOficiales = $dom->createElement('CaracteristicasOficiales');
    addElementIfExists($dom, $caracteristicasOficiales, 'NumeroFolio', 'TC-' . str_pad($tarjeta['ID_Tarjeta_Circulacion'], 6, '0', STR_PAD_LEFT));
    addElementIfExists($dom, $caracteristicasOficiales, 'FolioHolograma', 'H' . date('Y') . '-' . str_pad($tarjeta['ID_Tarjeta_Circulacion'], 8, '0', STR_PAD_LEFT));
    addElementIfExists($dom, $caracteristicasOficiales, 'AutoridadEmisora', 'SECRETARÍA DE FINANZAS DEL ESTADO');
    addElementIfExists($dom, $caracteristicasOficiales, 'FuncionarioAutorizante', 'DIRECTOR DE CONTROL VEHICULAR');
    
    // Agregar características oficiales al root
    $root->appendChild($caracteristicasOficiales);
    
    // Añadir metadatos
    $metadatos = $dom->createElement('Metadatos');
    addElementIfExists($dom, $metadatos, 'FechaGeneracion', date('Y-m-d H:i:s'));
    addElementIfExists($dom, $metadatos, 'GeneradoPor', $_SESSION['user_name'] ?? 'Sistema de Control Vehicular');
    addElementIfExists($dom, $metadatos, 'Version', '1.0');
    addElementIfExists($dom, $metadatos, 'DocumentoOficial', 'Este documento es una representación digital de la Tarjeta de Circulación oficial');
    
    // Agregar metadatos al root
    $root->appendChild($metadatos);
    
    // Establecer las cabeceras para la descarga
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="Tarjeta_Circulacion_' . $tarjeta['Placas'] . '.xml"');
    
    // Generar el XML como string y enviarlo al navegador
    echo $dom->saveXML();
    exit;
    
} catch (Exception $e) {
    // Si hay algún error en la generación del XML
    header("Content-Type: text/html; charset=utf-8");
    echo "<h1>Error generando el XML</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}
?>