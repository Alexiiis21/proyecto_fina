<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Verificar autenticación
if (function_exists('redirect_if_not_authenticated')) {
    redirect_if_not_authenticated('/auth/login.php', false);
} elseif (!isset($_SESSION['user_id'])) {
    exit("Acceso no autorizado");
}

// Verificar que se proporcionó un ID válido
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    exit("ID de multa no válido");
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               l.NumeroLicencia, l.TipoLicencia, l.FechaExpedicion as FechaExpedicionLic, l.Vigencia as FechaVencimientoLic,
               c.Nombre as NombreConductor, c.CURP, c.RFC, c.Telefono, c.CorreoElectronico, 
               o.Nombre as NombreOficial, o.NumeroIdentificacion as NumeroIdentificacionOficial, o.Cargo,
               cv.Nombre as CentroVerificacionNombre,
               tc.Placas, tc.FechaExpedicion as FechaExpedicionTC,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor, 
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio, d.Estado, d.CodigoPostal
        FROM multas m
        LEFT JOIN licencias l ON m.ID_Licencia = l.ID_Licencia
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN domicilios d ON c.ID_Domicilio = d.ID_Domicilio
        LEFT JOIN oficiales o ON m.ID_Oficial = o.ID_Oficial
        LEFT JOIN centrosverificacion cv ON o.ID_Centro_Verificacion = cv.ID_Centro_Verificacion
        LEFT JOIN tarjetascirculacion tc ON m.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        WHERE m.ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $multa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$multa) {
        exit("La multa no existe");
    }
} catch (PDOException $e) {
    exit("Error al cargar los datos de la multa: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT p.* FROM pagos p
        WHERE p.ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pago = null;
}

// Crear el documento XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

// Elemento raíz
$root = $xml->createElement('Multa');
$root = $xml->appendChild($root);

// Añadir atributos al elemento raíz
$root->setAttribute('id', $multa['ID_Multa']);
$root->setAttribute('fecha', $multa['Fecha']);
$root->setAttribute('fechaGeneracion', date('Y-m-d\TH:i:s'));

// Información de la multa
$multaElem = $xml->createElement('InformacionMulta');
$multaElem = $root->appendChild($multaElem);

// Datos básicos de la multa
$multaElem->appendChild($xml->createElement('Folio', $multa['ID_Multa']));
$multaElem->appendChild($xml->createElement('Fecha', $multa['Fecha']));
$multaElem->appendChild($xml->createElement('Motivo', htmlspecialchars($multa['Motivo'])));
$multaElem->appendChild($xml->createElement('Importe', $multa['Importe']));

// Información del conductor
if (!empty($multa['NombreConductor'])) {
    $conductorElem = $xml->createElement('Conductor');
    $conductorElem = $root->appendChild($conductorElem);
    
    $conductorElem->appendChild($xml->createElement('Nombre', htmlspecialchars($multa['NombreConductor'])));
    
    if (!empty($multa['CURP'])) {
        $conductorElem->appendChild($xml->createElement('CURP', $multa['CURP']));
    }
    
    if (!empty($multa['RFC'])) {
        $conductorElem->appendChild($xml->createElement('RFC', $multa['RFC']));
    }
    
    if (!empty($multa['Telefono'])) {
        $conductorElem->appendChild($xml->createElement('Telefono', $multa['Telefono']));
    }
    
    if (!empty($multa['CorreoElectronico'])) {
        $conductorElem->appendChild($xml->createElement('CorreoElectronico', $multa['CorreoElectronico']));
    }
    
    // Licencia
    if (!empty($multa['NumeroLicencia'])) {
        $licenciaElem = $xml->createElement('Licencia');
        $licenciaElem = $conductorElem->appendChild($licenciaElem);
        
        $licenciaElem->appendChild($xml->createElement('Numero', $multa['NumeroLicencia']));
        
        if (!empty($multa['TipoLicencia'])) {
            $licenciaElem->appendChild($xml->createElement('Tipo', $multa['TipoLicencia']));
        }
        
        if (!empty($multa['FechaExpedicionLic'])) {
            $licenciaElem->appendChild($xml->createElement('FechaExpedicion', $multa['FechaExpedicionLic']));
        }
        
        if (!empty($multa['FechaVencimientoLic'])) {
            $licenciaElem->appendChild($xml->createElement('FechaVencimiento', $multa['FechaVencimientoLic']));
        }
    }
    
    // Domicilio
    if (!empty($multa['Calle'])) {
        $domicilioElem = $xml->createElement('Domicilio');
        $domicilioElem = $conductorElem->appendChild($domicilioElem);
        
        $domicilioElem->appendChild($xml->createElement('Calle', htmlspecialchars($multa['Calle'])));
        
        if (!empty($multa['NumeroExterior'])) {
            $domicilioElem->appendChild($xml->createElement('NumeroExterior', $multa['NumeroExterior']));
        }
        
        if (!empty($multa['NumeroInterior'])) {
            $domicilioElem->appendChild($xml->createElement('NumeroInterior', $multa['NumeroInterior']));
        }
        
        if (!empty($multa['Colonia'])) {
            $domicilioElem->appendChild($xml->createElement('Colonia', htmlspecialchars($multa['Colonia'])));
        }
        
        if (!empty($multa['Municipio'])) {
            $domicilioElem->appendChild($xml->createElement('Municipio', htmlspecialchars($multa['Municipio'])));
        }
        
        if (!empty($multa['Estado'])) {
            $domicilioElem->appendChild($xml->createElement('Estado', htmlspecialchars($multa['Estado'])));
        }
        
        if (!empty($multa['CodigoPostal'])) {
            $domicilioElem->appendChild($xml->createElement('CodigoPostal', $multa['CodigoPostal']));
        }
    }
}

// Información del vehículo
if (!empty($multa['Placas']) || !empty($multa['Marca'])) {
    $vehiculoElem = $xml->createElement('Vehiculo');
    $vehiculoElem = $root->appendChild($vehiculoElem);
    
    if (!empty($multa['Placas'])) {
        $vehiculoElem->appendChild($xml->createElement('Placas', $multa['Placas']));
    }
    
    if (!empty($multa['Marca'])) {
        $vehiculoElem->appendChild($xml->createElement('Marca', htmlspecialchars($multa['Marca'])));
    }
    
    if (!empty($multa['Modelo'])) {
        $vehiculoElem->appendChild($xml->createElement('Modelo', htmlspecialchars($multa['Modelo'])));
    }
    
    if (!empty($multa['AnoFabricacion'])) {
        $vehiculoElem->appendChild($xml->createElement('Año', $multa['AnoFabricacion']));
    }
    
    if (!empty($multa['Color'])) {
        $vehiculoElem->appendChild($xml->createElement('Color', htmlspecialchars($multa['Color'])));
    }
    
    if (!empty($multa['NumeroSerie'])) {
        $vehiculoElem->appendChild($xml->createElement('NumeroSerie', $multa['NumeroSerie']));
    }
    
    if (!empty($multa['NumeroMotor'])) {
        $vehiculoElem->appendChild($xml->createElement('NumeroMotor', $multa['NumeroMotor']));
    }
    
    // Tarjeta de circulación
    if (!empty($multa['FechaExpedicionTC'])) {
        $tarjetaElem = $xml->createElement('TarjetaCirculacion');
        $tarjetaElem = $vehiculoElem->appendChild($tarjetaElem);
        
        $tarjetaElem->appendChild($xml->createElement('FechaExpedicion', $multa['FechaExpedicionTC']));
    }
}

// Información del oficial
if (!empty($multa['NombreOficial'])) {
    $oficialElem = $xml->createElement('Oficial');
    $oficialElem = $root->appendChild($oficialElem);
    
    $oficialElem->appendChild($xml->createElement('Nombre', htmlspecialchars($multa['NombreOficial'])));
    
    if (!empty($multa['NumeroIdentificacionOficial'])) {
        $oficialElem->appendChild($xml->createElement('NumeroIdentificacion', $multa['NumeroIdentificacionOficial']));
    }
    
    if (!empty($multa['Cargo'])) {
        $oficialElem->appendChild($xml->createElement('Cargo', htmlspecialchars($multa['Cargo'])));
    }
    
    if (!empty($multa['CentroVerificacionNombre'])) {
        $oficialElem->appendChild($xml->createElement('CentroVerificacion', htmlspecialchars($multa['CentroVerificacionNombre'])));
    }
}

if ($pago) {
    $pagoElem = $xml->createElement('Pago');
    $pagoElem = $root->appendChild($pagoElem);
    
    $pagoElem->appendChild($xml->createElement('NumeroTransaccion', $pago['NumeroTransaccion']));
    
    if (!empty($pago['FechaPago'])) {
        $pagoElem->appendChild($xml->createElement('FechaPago', $pago['FechaPago']));
    }
    
    if (!empty($pago['Importe'])) {
        $pagoElem->appendChild($xml->createElement('Importe', $pago['Importe']));
    }
    
    if (!empty($pago['MetodoPago'])) {
        $pagoElem->appendChild($xml->createElement('Metodo', $pago['MetodoPago']));
    }
}

// Configurar encabezados para descarga
$nombreArchivo = "Multa_" . $id . "_" . date("Ymd") . ".xml";
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Pragma: no-cache');
header('Cache-Control: must-revalidate');
header('Content-Length: ' . strlen($xml->saveXML()));

// Salida del XML
echo $xml->saveXML();
?>