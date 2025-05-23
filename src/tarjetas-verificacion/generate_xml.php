<?php
// Iniciar buffer de salida
ob_start();

// Incluir archivos necesarios
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo "<h1>Error de acceso</h1>";
    echo "<p>Debe iniciar sesión para acceder a esta función.</p>";
    echo "<p><a href='../auth/login.php'>Iniciar sesión</a></p>";
    exit;
}

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo "<h1>Error de solicitud</h1>";
    echo "<p>ID de tarjeta de verificación no válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos de la tarjeta
try {
    // Obtener información completa de la tarjeta
    $stmt = $pdo->prepare("
        SELECT tv.*, 
               v.Marca, v.Modelo, v.Placas, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor,
               v.TipoCarroceria, v.NumeroAsientos, v.Cilindraje, v.TipoCombustible, v.Uso, v.Transmision, v.NumeroPuertas, v.Clase,
               cv.Nombre as CentroVerificacionNombre, cv.Direccion as CentroVerificacionDireccion, 
               cv.NumeroCentroVerificacion, 
               tc.Placas as PlacasCirculacion, tc.ID_Tarjeta_Circulacion as IDTarjetaCirculacion
        FROM tarjetasverificacion tv
        LEFT JOIN vehiculos v ON tv.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN centrosverificacion cv ON tv.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON tv.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        WHERE tv.ID_Tarjeta_Verificacion = ?
    ");
    $stmt->execute([$id]);
    $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tarjeta) {
        http_response_code(404); // Not Found
        echo "<h1>Error: Tarjeta no encontrada</h1>";
        echo "<p>La tarjeta solicitada no existe.</p>";
        echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo "<h1>Error de base de datos</h1>";
    echo "<p>Error al cargar los datos de la tarjeta: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

// Limpiar buffer antes de generar el XML
ob_clean();

// Determinar semestre y periodo de vigencia
$fechaExpedicion = new DateTime($tarjeta['FechaExpedicion']);
$semestre = (int)$fechaExpedicion->format('n') <= 6 ? 1 : 2;
$anoExpedicion = $fechaExpedicion->format('Y');

// Calcular periodo de vigencia
if ($semestre == 1) {
    $periodoVigencia = "Primer semestre " . $anoExpedicion;
} else {
    $periodoVigencia = "Segundo semestre " . $anoExpedicion;
}

// Crear el documento XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// Crear el elemento raíz
$rootElement = $xml->createElement('TarjetaVerificacion');
$rootElement = $xml->appendChild($rootElement);

// Agregar atributos al elemento raíz
$rootElement->setAttribute('id', $tarjeta['ID_Tarjeta_Verificacion']);
$rootElement->setAttribute('folioCertificado', $tarjeta['FolioCertificado']);
$rootElement->setAttribute('fechaExpedicion', $tarjeta['FechaExpedicion']);
$rootElement->setAttribute('vigencia', $tarjeta['Vigencia']);

// Agregar información general
$infoGeneral = $xml->createElement('InformacionGeneral');
$infoGeneral = $rootElement->appendChild($infoGeneral);

// Elementos de información general
$expedicion = $xml->createElement('FechaExpedicion', $tarjeta['FechaExpedicion']);
$infoGeneral->appendChild($expedicion);

$horaEntrada = $xml->createElement('HoraEntrada', $tarjeta['HoraEntrada']);
$infoGeneral->appendChild($horaEntrada);

$horaSalida = $xml->createElement('HoraSalida', $tarjeta['HoraSalida']);
$infoGeneral->appendChild($horaSalida);

$motivo = $xml->createElement('MotivoVerificacion', $tarjeta['MotivoVerificacion']);
$infoGeneral->appendChild($motivo);

$vigencia = $xml->createElement('Vigencia', $tarjeta['Vigencia']);
$infoGeneral->appendChild($vigencia);

$periodoElement = $xml->createElement('PeriodoVigencia', $periodoVigencia);
$infoGeneral->appendChild($periodoElement);

$semestreElement = $xml->createElement('Semestre', $semestre);
$infoGeneral->appendChild($semestreElement);

$numeroDeSerie = $xml->createElement('NumeroSerieVehiculo', $tarjeta['NumeroSerieVehiculo']);
$infoGeneral->appendChild($numeroDeSerie);

// Agregar información del centro de verificación
$centroVerif = $xml->createElement('CentroVerificacion');
$centroVerif = $rootElement->appendChild($centroVerif);

$centroVerif->setAttribute('id', $tarjeta['ID_Centro_Verificacion']);

$nombreCentro = $xml->createElement('Nombre', $tarjeta['CentroVerificacionNombre'] ?? 'No especificado');
$centroVerif->appendChild($nombreCentro);

$direccionCentro = $xml->createElement('Direccion', $tarjeta['CentroVerificacionDireccion'] ?? 'No especificado');
$centroVerif->appendChild($direccionCentro);

$numeroCentro = $xml->createElement('NumeroCentro', $tarjeta['NumeroCentroVerificacion'] ?? 'No especificado');
$centroVerif->appendChild($numeroCentro);

// Agregar información del vehículo
$vehiculo = $xml->createElement('Vehiculo');
$vehiculo = $rootElement->appendChild($vehiculo);

$vehiculo->setAttribute('id', $tarjeta['ID_Vehiculo']);

$marca = $xml->createElement('Marca', $tarjeta['Marca'] ?? 'No especificado');
$vehiculo->appendChild($marca);

$modelo = $xml->createElement('Modelo', $tarjeta['Modelo'] ?? 'No especificado');
$vehiculo->appendChild($modelo);

$anoFabricacion = $xml->createElement('AnoFabricacion', $tarjeta['AnoFabricacion'] ?? 'No especificado');
$vehiculo->appendChild($anoFabricacion);

$placas = $xml->createElement('Placas', $tarjeta['Placas'] ?? 'No especificado');
$vehiculo->appendChild($placas);

$color = $xml->createElement('Color', $tarjeta['Color'] ?? 'No especificado');
$vehiculo->appendChild($color);

$numeroSerie = $xml->createElement('NumeroSerie', $tarjeta['NumeroSerie'] ?? 'No especificado');
$vehiculo->appendChild($numeroSerie);

$numeroMotor = $xml->createElement('NumeroMotor', $tarjeta['NumeroMotor'] ?? 'No especificado');
$vehiculo->appendChild($numeroMotor);

$tipoCombustible = $xml->createElement('TipoCombustible', $tarjeta['TipoCombustible'] ?? 'No especificado');
$vehiculo->appendChild($tipoCombustible);

$tipoCarroceria = $xml->createElement('TipoCarroceria', $tarjeta['TipoCarroceria'] ?? 'No especificado');
$vehiculo->appendChild($tipoCarroceria);

$clase = $xml->createElement('Clase', $tarjeta['Clase'] ?? 'No especificado');
$vehiculo->appendChild($clase);

$cilindraje = $xml->createElement('Cilindraje', $tarjeta['Cilindraje'] ?? 'No especificado');
$vehiculo->appendChild($cilindraje);

$uso = $xml->createElement('Uso', $tarjeta['Uso'] ?? 'No especificado');
$vehiculo->appendChild($uso);

// Agregar referencia a la tarjeta de circulación
$tarjetaCirculacion = $xml->createElement('TarjetaCirculacion');
$tarjetaCirculacion = $rootElement->appendChild($tarjetaCirculacion);

$tarjetaCirculacion->setAttribute('id', $tarjeta['IDTarjetaCirculacion'] ?? '0');
$placasCirculacion = $xml->createElement('Placas', $tarjeta['PlacasCirculacion'] ?? 'No especificado');
$tarjetaCirculacion->appendChild($placasCirculacion);

// Agregar información adicional si es necesario
$adicional = $xml->createElement('InformacionAdicional');
$adicional = $rootElement->appendChild($adicional);

$timestamp = $xml->createElement('FechaGeneracion', date('Y-m-d H:i:s'));
$adicional->appendChild($timestamp);

$generadoPor = $xml->createElement('GeneradoPor', $_SESSION['user_name'] ?? 'Sistema');
$adicional->appendChild($generadoPor);

// Establecer headers para descarga
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="TarjetaVerificacion_' . $tarjeta['FolioCertificado'] . '.xml"');

// Devolver el XML como string
echo $xml->saveXML();
exit;
?>