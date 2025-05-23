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
    echo "<p>ID de tarjeta de circulación no válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos de la tarjeta
try {
    // Obtener información completa de la tarjeta
    $stmt = $pdo->prepare("
        SELECT tc.*, 
               v.Marca, v.Modelo, v.Placas as VehiculoPlacas, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor,
               v.TipoCarroceria, v.NumeroAsientos, v.Cilindraje, v.TipoCombustible, v.Uso, v.Transmision, v.NumeroPuertas, v.Clase,
               p.Nombre as PropietarioNombre, p.RFC, p.CURP, p.Telefono, p.CorreoElectronico,
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio as DomicilioMunicipio, d.Estado as DomicilioEstado, d.CodigoPostal
        FROM tarjetascirculacion tc
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios p ON tc.ID_Propietario = p.ID_Propietario
        LEFT JOIN domicilios d ON p.ID_Domicilio = d.ID_Domicilio
        WHERE tc.ID_Tarjeta_Circulacion = ?
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

// Limpiar buffer antes de generar el PDF
ob_clean();

// Verificar si FPDF está instalado correctamente
$fpdfPath = '../vendor/fpdf/fpdf.php';
if (!file_exists($fpdfPath)) {
    http_response_code(500); // Internal Server Error
    echo "<h1>Error: FPDF no encontrado</h1>";
    echo "<p>No se encontró la biblioteca FPDF en: " . htmlspecialchars($fpdfPath) . "</p>";
    echo "<p>Para solucionar este problema:</p>";
    echo "<ol>";
    echo "<li>Descargue la biblioteca FPDF desde <a href='http://www.fpdf.org/'>http://www.fpdf.org/</a></li>";
    echo "<li>Cree la carpeta 'vendor/fpdf' en su proyecto</li>";
    echo "<li>Copie el archivo fpdf.php y la carpeta 'font' en esa ubicación</li>";
    echo "</ol>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

// Intentar generar el PDF
try {
    // Incluir la biblioteca FPDF
    require_once($fpdfPath);
    
    // Preparar datos para el PDF
    $nombrePropietario = $tarjeta['PropietarioNombre'] ?? 'No especificado';
    $placas = $tarjeta['Placas'];
    $rfc = $tarjeta['RFC'] ?? 'No especificado';
    $numeroSerie = $tarjeta['NumeroSerie'] ?? 'No especificado';
    $modelo = $tarjeta['AnoFabricacion'] ?? 'No especificado';
    $localidad = $tarjeta['Localidad'] ?? 'No especificado';
    $marcaModelo = ($tarjeta['Marca'] ?? '') . '/' . ($tarjeta['Modelo'] ?? '') . '/' . ($tarjeta['TipoCarroceria'] ?? '');
    $municipio = $tarjeta['Municipio'] ?? 'No especificado';
    $origen = $tarjeta['Origen'] ?? 'MEXICANO';
    $color = $tarjeta['Color'] ?? 'No especificado';
    $transmision = $tarjeta['Transmision'] ?? 'ESTANDAR';
    $fechaExpedicion = date('d-M-y', strtotime($tarjeta['FechaExpedicion']));
    $numeroMotor = $tarjeta['NumeroMotor'] ?? 'No especificado';
    $tipoServicio = $tarjeta['TipoServicio'] ?? 'PARTICULAR';
    
    // Generar folio único
    $folio = str_pad($tarjeta['ID_Tarjeta_Circulacion'], 9, '0', STR_PAD_LEFT);
    $holograma = 'H' . date('Y') . '-' . $folio;
    $operacion = date('Y') . '/' . str_pad($id, 7, '0', STR_PAD_LEFT);
    
    // Ruta de las imágenes
    $imgPath = '../img/';
    
    // Crear nueva instancia de PDF con tamaño tarjeta
    $pdf = new FPDF('L', 'mm', [85.6, 53.98]);
    
    // Añadir página
    $pdf->AddPage();
    
    // Imagen de fondo (verifica que exista)
    if (file_exists($imgPath . 'fondo2.jpeg')) {
        $pdf->Image($imgPath . 'fondo2.jpeg', 0, 0, 85.6, 53.98);
    } else {
        // Si no existe, crear un rectángulo como fondo
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, 85.6, 53.98, 'F');
    }
    
    $pdf->SetAutoPageBreak(false);

    // Encabezados
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 4);
    $pdf->Cell(0, 4, 'TIPO DE SERVICIO', 0, 1);
    $pdf->SetXY(27, 4);
    $pdf->Cell(0, 4, 'HOLOGRAMA', 0, 1);
    $pdf->SetXY(37, 4);
    $pdf->Cell(0, 4, 'FOLIO', 0, 1);
    $pdf->SetXY(50, 4);
    $pdf->Cell(0, 4, 'VIGENCIA', 0, 1);
    $pdf->SetXY(61, 4);
    $pdf->Cell(0, 4, 'PLACA', 0, 1);
    
    // Datos principales
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(4, 6);
    $pdf->Cell(0, 4, $tipoServicio, 0, 1);
    $pdf->SetXY(27, 6);
    $pdf->Cell(0, 4, $holograma, 0, 1);
    $pdf->SetXY(37, 6);
    $pdf->Cell(0, 4, $folio, 0, 1);
    $pdf->SetXY(50, 6);
    $pdf->Cell(0, 4, date('d/m/Y', strtotime($tarjeta['FechaVencimiento'])), 0, 1);
    
    // Placa - más grande y en negrita
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetXY(61, 6.5);
    $pdf->Cell(0, 4, $placas, 0, 1);
    
    // Propietario
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 8);
    $pdf->Cell(0, 4, 'PROPIETARIO', 0, 1);
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(15, 8);
    $pdf->Cell(0, 4, $nombrePropietario, 0, 1);
    
    // RFC y datos del vehículo
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 13);
    $pdf->Cell(0, 4, 'RFC', 0, 1);
    $pdf->SetXY(25, 13);
    $pdf->Cell(0, 4, 'NUMERO DE SERIE', 0, 1);
    $pdf->SetXY(53, 13);
    $pdf->Cell(0, 4, 'MODELO', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(4, 15);
    $pdf->Cell(0, 4, $rfc, 0, 1);
    $pdf->SetXY(25, 15);
    $pdf->Cell(0, 4, $numeroSerie, 0, 1);
    $pdf->SetXY(53, 15);
    $pdf->Cell(0, 4, $modelo, 0, 1);
    
    // Localidad y marca
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 16.5);
    $pdf->Cell(0, 4, 'LOCALIDAD', 0, 1);
    $pdf->SetXY(25, 16.5);
    $pdf->Cell(0, 4, 'MARCA/LINEA/SUBLINEA', 0, 1);
    $pdf->SetXY(53, 16.5);
    $pdf->Cell(0, 4, 'OPERACION', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(4, 18.5);
    $pdf->Cell(0, 4, $localidad, 0, 1);
    $pdf->SetXY(25, 18.5);
    $pdf->Cell(0, 4, $marcaModelo, 0, 1);
    $pdf->SetXY(53, 18.5);
    $pdf->Cell(0, 4, $operacion, 0, 1);
    
    // Municipio y folio
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 22);
    $pdf->Cell(0, 4, 'MUNICIPIO', 0, 1);
    $pdf->SetXY(53, 20);
    $pdf->Cell(0, 4, 'FOLIO', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(4, 24);
    $pdf->Cell(0, 4, $municipio, 0, 1);
    $pdf->SetXY(53, 21.5);
    $pdf->Cell(0, 4, 'A ' . str_pad($id, 7, '0', STR_PAD_LEFT), 0, 1);
    
    // Placa anterior si existe
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(53, 23);
    $pdf->Cell(0, 4, 'PLACA ANT.', 0, 1);
    
    // Número de constancia
    $pdf->SetXY(4, 28);
    $pdf->Cell(0, 4, 'NUMERO DE CONSTANCIA', 0, 1);
    $pdf->SetXY(4, 29.3);
    $pdf->Cell(0, 4, 'DE INSCRIPCION AICD', 0, 1);
    
    // Características del vehículo
    $pdf->SetXY(25, 27);
    $pdf->Cell(0, 4, 'CILINDRAJE', 0, 1);
    $pdf->SetXY(25, 29);
    $pdf->Cell(0, 4, 'CAPACIDAD', 0, 1);
    $pdf->SetXY(25, 31);
    $pdf->Cell(0, 4, 'PUERTAS', 0, 1);
    $pdf->SetXY(25, 33);
    $pdf->Cell(0, 4, 'ASIENTOS', 0, 1);
    $pdf->SetXY(25, 35);
    $pdf->Cell(0, 4, 'COMBUSTIBLE', 0, 1);
    $pdf->SetXY(25, 37);
    $pdf->Cell(0, 4, 'TRANSMISION', 0, 1);
    
    // Valores de las características
    $pdf->SetXY(35, 27);
    $pdf->Cell(0, 4, $tarjeta['Cilindraje'] ?? '4', 0, 1);
    $pdf->SetXY(35, 31);
    $pdf->Cell(0, 4, $tarjeta['NumeroPuertas'] ?? '2', 0, 1);
    $pdf->SetXY(35, 33);
    $pdf->Cell(0, 4, $tarjeta['NumeroAsientos'] ?? '3', 0, 1);
    $pdf->SetXY(35, 35);
    $pdf->Cell(0, 4, ($tarjeta['TipoCombustible'] == 'GASOLINA' ? '1' : '2'), 0, 1);
    
    // Códigos de vehículo
    $pdf->SetXY(37, 27);
    $pdf->Cell(0, 4, 'CVE VEHICULAR', 0, 1);
    $pdf->SetXY(37, 31);
    $pdf->Cell(0, 4, 'CLASE', 0, 1);
    $pdf->SetXY(37, 33);
    $pdf->Cell(0, 4, 'TIPO', 0, 1);
    $pdf->SetXY(37, 35);
    $pdf->Cell(0, 4, 'USO', 0, 1);
    $pdf->SetXY(37, 37);
    $pdf->Cell(0, 4, 'RPA', 0, 1);
    
    // Valores de códigos
    $pdf->SetFont('Arial', '', 2.7);
    $pdf->SetXY(35, 28.6);
    $pdf->Cell(0, 4, '750', 0, 1);
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(44, 31);
    $pdf->Cell(0, 4, substr($tarjeta['Clase'] ?? '2', 0, 1), 0, 1);
    $pdf->SetXY(44, 33);
    $pdf->Cell(0, 4, '9', 0, 1);
    $pdf->SetXY(44, 35);
    $pdf->Cell(0, 4, '36', 0, 1);
    
    // Origen y color
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(4, 33);
    $pdf->Cell(0, 4, 'ORIGEN', 0, 1);
    $pdf->SetXY(4, 37);
    $pdf->Cell(0, 4, 'COLOR', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(4, 35);
    $pdf->Cell(0, 4, $origen, 0, 1);
    $pdf->SetXY(4, 39);
    $pdf->Cell(0, 4, $color, 0, 1);
    $pdf->SetXY(25, 39);
    $pdf->Cell(0, 4, $transmision, 0, 1);
    
    // Fechas y datos adicionales
    $pdf->SetFont('Arial', '', 3.5);
    $pdf->SetXY(53, 27);
    $pdf->Cell(0, 4, 'FECHA DE EXPEDICION', 0, 1);
    $pdf->SetXY(53, 31);
    $pdf->Cell(0, 4, 'OFICINA EXPEDIDORA', 0, 1);
    $pdf->SetXY(53, 32.5);
    $pdf->Cell(0, 4, 'MOVIMIENTO', 0, 1);
    $pdf->SetXY(53, 37);
    $pdf->Cell(0, 4, 'NUMERO DE MOTOR', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(53, 29);
    $pdf->Cell(0, 4, strtoupper($fechaExpedicion), 0, 1);
    $pdf->SetXY(53, 35);
    $pdf->Cell(0, 4, 'ALTA DE PLACA', 0, 1);
    $pdf->SetXY(53, 39);
    $pdf->Cell(0, 4, $numeroMotor, 0, 1);
    
    // Imágenes adicionales
    if (file_exists($imgPath . 'texto.png')) {
        $pdf->Image($imgPath . 'texto.png', 14, 40, 60);
    }
    if (file_exists($imgPath . 'qr.jpeg')) {
        $pdf->Image($imgPath . 'qr.jpeg', 72, 40, 12);
    }
    if (file_exists($imgPath . 'esq1.png')) {
        $pdf->Image($imgPath . 'esq1.png', 10, 37, 14);
    }
    if (file_exists($imgPath . 'esccudos.png')) {
        $pdf->Image($imgPath . 'esccudos.png', 25, 37.5, 12.5);
    }
    if (file_exists($imgPath . 'liazul.png')) {
        $pdf->Image($imgPath . 'liazul.png', 4, 39, 15);
        $pdf->Image($imgPath . 'liazul.png', 19, 39, 15);
        $pdf->Image($imgPath . 'liazul.png', 34, 39, 15);
        $pdf->Image($imgPath . 'liazul.png', 49, 39, 15);
        $pdf->Image($imgPath . 'liazul.png', 55, 39, 15);
        $pdf->Image($imgPath . 'liazul.png', 71, 37.5, 14);
    }
    if (file_exists($imgPath . 'inversa.png')) {
        $pdf->Image($imgPath . 'inversa.png', 72, 10, 12);
    }
    
    // Pie de página con datos del gobierno
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetXY(37, 42);
    $pdf->Cell(0, 4, 'PODER EJECUTIVO DEL', 0, 1);
    $pdf->SetXY(37, 44);
    $pdf->Cell(0, 4, 'ESTADO DE QUERETARO', 0, 1);
    $pdf->SetFont('Arial', '', 3.6);
    $pdf->SetXY(37, 46);
    $pdf->Cell(0, 4, 'SECRETARIA DE PLANEACION Y FINANZAS', 0, 1);
    
    // Generar el PDF
    $pdfFilename = 'Tarjeta_Circulacion_' . $placas . '.pdf';
    $pdf->Output('D', $pdfFilename);
    exit;
    
} catch (Exception $e) {
    // Si hay algún error en la generación del PDF
    http_response_code(500); // Internal Server Error
    echo "<h1>Error generando el PDF</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}
?>