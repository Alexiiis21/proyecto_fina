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
    echo "<p>ID de pago no válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos completos del pago
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               tc.ID_Tarjeta_Circulacion, tc.Placas, tc.FechaExpedicion as TC_FechaExpedicion, 
               tc.FechaVencimiento as TC_FechaVencimiento,
               v.ID_Vehiculo, v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, 
               v.NumeroMotor, v.TipoCombustible, v.Cilindraje, v.Transmision, v.Clase, v.TipoCarroceria,
               pr.ID_Propietario, pr.Nombre as PropietarioNombre, 
               pr.RFC, pr.CURP, pr.Telefono, pr.CorreoElectronico
        FROM pagos p
        LEFT JOIN tarjetascirculacion tc ON p.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios pr ON tc.ID_Propietario = pr.ID_Propietario
        WHERE p.ID_Pago = ?
    ");
    $stmt->execute([$id]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pago) {
        http_response_code(404); 
        echo "<h1>Error: Pago no encontrado</h1>";
        echo "<p>El pago solicitado no existe.</p>";
        echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500); 
    echo "<h1>Error de base de datos</h1>";
    echo "<p>Error al cargar los datos del pago: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

ob_clean();

// Crear el documento XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// Crear el elemento raíz
$rootElement = $xml->createElement('Pago');
$rootElement = $xml->appendChild($rootElement);

// Agregar atributos al elemento raíz
$rootElement->setAttribute('id', $pago['ID_Pago']);
$rootElement->setAttribute('numeroTransaccion', $pago['NumeroTransaccion']);
$rootElement->setAttribute('fechaGeneracion', date('Y-m-d H:i:s'));

// Información del pago
$infoElement = $xml->createElement('InformacionPago');
$infoElement = $rootElement->appendChild($infoElement);

// Agregar elementos de información de pago
if (!empty($pago['LineaCaptura'])) {
    $lineaCaptura = $xml->createElement('LineaCaptura', $pago['LineaCaptura']);
    $infoElement->appendChild($lineaCaptura);
}

$fechaLimite = $xml->createElement('FechaLimite', $pago['FechaLimite']);
$infoElement->appendChild($fechaLimite);

$fechaPago = $xml->createElement('FechaPago', $pago['FechaPago']);
$infoElement->appendChild($fechaPago);

$importe = $xml->createElement('Importe', $pago['Importe']);
$infoElement->appendChild($importe);

$metodoPago = $xml->createElement('MetodoPago', $pago['MetodoPago']);
$infoElement->appendChild($metodoPago);

// Estado del pago (si se pagó a tiempo o con retraso)
$fechaPagoObj = new DateTime($pago['FechaPago']);
$fechaLimiteObj = new DateTime($pago['FechaLimite']);
if ($fechaPagoObj > $fechaLimiteObj) {
    $diferencia = $fechaPagoObj->diff($fechaLimiteObj)->days;
    $estadoPago = $xml->createElement('EstadoPago', 'Tardío');
    $estadoPago->setAttribute('diasRetraso', $diferencia);
} else {
    $estadoPago = $xml->createElement('EstadoPago', 'A tiempo');
}
$infoElement->appendChild($estadoPago);

// Información de la tarjeta de circulación
if (!empty($pago['ID_Tarjeta_Circulacion'])) {
    $tarjetaElement = $xml->createElement('TarjetaCirculacion');
    $tarjetaElement = $rootElement->appendChild($tarjetaElement);
    $tarjetaElement->setAttribute('id', $pago['ID_Tarjeta_Circulacion']);
    
    if (!empty($pago['Placas'])) {
        $placas = $xml->createElement('Placas', $pago['Placas']);
        $tarjetaElement->appendChild($placas);
    }
    
    if (!empty($pago['TC_FechaExpedicion'])) {
        $expedicion = $xml->createElement('FechaExpedicion', $pago['TC_FechaExpedicion']);
        $tarjetaElement->appendChild($expedicion);
    }
    
    if (!empty($pago['TC_FechaVencimiento'])) {
        $vencimiento = $xml->createElement('FechaVencimiento', $pago['TC_FechaVencimiento']);
        $tarjetaElement->appendChild($vencimiento);
        
        // Estado de la tarjeta (vigente, por vencer, vencida)
        $hoy = new DateTime();
        $vencimientoObj = new DateTime($pago['TC_FechaVencimiento']);
        
        if ($hoy > $vencimientoObj) {
            $estadoTarjeta = $xml->createElement('EstadoTarjeta', 'Vencida');
        } else {
            $diferencia = $hoy->diff($vencimientoObj)->days;
            if ($diferencia <= 30) {
                $estadoTarjeta = $xml->createElement('EstadoTarjeta', 'Por vencer');
                $estadoTarjeta->setAttribute('diasRestantes', $diferencia);
            } else {
                $estadoTarjeta = $xml->createElement('EstadoTarjeta', 'Vigente');
                $estadoTarjeta->setAttribute('diasRestantes', $diferencia);
            }
        }
        $tarjetaElement->appendChild($estadoTarjeta);
    }
    
    if (!empty($pago['TC_NumeroSerie'])) {
        $serie = $xml->createElement('NumeroSerie', $pago['TC_NumeroSerie']);
        $tarjetaElement->appendChild($serie);
    }
}

// Información del vehículo
if (!empty($pago['ID_Vehiculo'])) {
    $vehiculoElement = $xml->createElement('Vehiculo');
    $vehiculoElement = $rootElement->appendChild($vehiculoElement);
    $vehiculoElement->setAttribute('id', $pago['ID_Vehiculo']);
    
    if (!empty($pago['Marca'])) {
        $marca = $xml->createElement('Marca', $pago['Marca']);
        $vehiculoElement->appendChild($marca);
    }
    
    if (!empty($pago['Modelo'])) {
        $modelo = $xml->createElement('Modelo', $pago['Modelo']);
        $vehiculoElement->appendChild($modelo);
    }
    
    if (!empty($pago['AnoFabricacion'])) {
        $ano = $xml->createElement('AnoFabricacion', $pago['AnoFabricacion']);
        $vehiculoElement->appendChild($ano);
    }
    
    if (!empty($pago['Color'])) {
        $color = $xml->createElement('Color', $pago['Color']);
        $vehiculoElement->appendChild($color);
    }
    
    if (!empty($pago['NumeroSerie'])) {
        $serie = $xml->createElement('NumeroSerie', $pago['NumeroSerie']);
        $vehiculoElement->appendChild($serie);
    }
    
    if (!empty($pago['NumeroMotor'])) {
        $motor = $xml->createElement('NumeroMotor', $pago['NumeroMotor']);
        $vehiculoElement->appendChild($motor);
    }
    
    if (!empty($pago['TipoCombustible'])) {
        $combustible = $xml->createElement('TipoCombustible', $pago['TipoCombustible']);
        $vehiculoElement->appendChild($combustible);
    }
    
    if (!empty($pago['Transmision'])) {
        $transmision = $xml->createElement('Transmision', $pago['Transmision']);
        $vehiculoElement->appendChild($transmision);
    }
    
    if (!empty($pago['Cilindraje'])) {
        $cilindraje = $xml->createElement('Cilindraje', $pago['Cilindraje']);
        $vehiculoElement->appendChild($cilindraje);
    }
    
    if (!empty($pago['Clase'])) {
        $clase = $xml->createElement('Clase', $pago['Clase']);
        $vehiculoElement->appendChild($clase);
    }
    
    if (!empty($pago['TipoCarroceria'])) {
        $carroceria = $xml->createElement('TipoCarroceria', $pago['TipoCarroceria']);
        $vehiculoElement->appendChild($carroceria);
    }
}

// Información del propietario
if (!empty($pago['ID_Propietario'])) {
    $propietarioElement = $xml->createElement('Propietario');
    $propietarioElement = $rootElement->appendChild($propietarioElement);
    $propietarioElement->setAttribute('id', $pago['ID_Propietario']);
    
    // Construir nombre completo
    $nombreCompleto = $pago['PropietarioNombre'];
    if (!empty($pago['ApellidoPaterno'])) {
        $nombreCompleto .= ' ' . $pago['ApellidoPaterno'];
    }
    if (!empty($pago['ApellidoMaterno'])) {
        $nombreCompleto .= ' ' . $pago['ApellidoMaterno'];
    }
    
    $nombre = $xml->createElement('Nombre', $nombreCompleto);
    $propietarioElement->appendChild($nombre);
    
    if (!empty($pago['RFC'])) {
        $rfc = $xml->createElement('RFC', $pago['RFC']);
        $propietarioElement->appendChild($rfc);
    }
    
    if (!empty($pago['CURP'])) {
        $curp = $xml->createElement('CURP', $pago['CURP']);
        $propietarioElement->appendChild($curp);
    }
    
    if (!empty($pago['Telefono'])) {
        $telefono = $xml->createElement('Telefono', $pago['Telefono']);
        $propietarioElement->appendChild($telefono);
    }
    
    if (!empty($pago['CorreoElectronico'])) {
        $correo = $xml->createElement('CorreoElectronico', $pago['CorreoElectronico']);
        $propietarioElement->appendChild($correo);
    }
}

// Agregar metadatos
$metadataElement = $xml->createElement('Metadata');
$metadataElement = $rootElement->appendChild($metadataElement);

$timestamp = $xml->createElement('FechaGeneracion', date('Y-m-d H:i:s'));
$metadataElement->appendChild($timestamp);

$generadoPor = $xml->createElement('GeneradoPor', $_SESSION['user_name'] ?? 'Sistema');
$metadataElement->appendChild($generadoPor);

$version = $xml->createElement('Version', '1.0');
$metadataElement->appendChild($version);

// Establecer headers para descarga
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="Pago_' . $pago['NumeroTransaccion'] . '.xml"');

// Devolver el XML como string
echo $xml->saveXML();
exit;
?>