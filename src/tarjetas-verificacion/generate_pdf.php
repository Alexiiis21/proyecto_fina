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
               tc.Placas as PlacasCirculacion
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
    $folioCertificado = $tarjeta['FolioCertificado'];
    $marca = $tarjeta['Marca'] ?? 'No especificado';
    $submarca = $tarjeta['Modelo'] ?? 'No especificado';
    $anoModelo = $tarjeta['AnoFabricacion'] ?? 'No especificado';
    $placas = $tarjeta['Placas'] ?? 'No especificado';
    $numeroSerie = $tarjeta['NumeroSerie'] ?? 'No especificado';
    $clase = $tarjeta['Clase'] ?? 'AUTOMOVIL_SEDAN';
    $tipoCombustible = $tarjeta['TipoCombustible'] ?? 'Gasolina';
    $niv = $tarjeta['NumeroSerie'] ?? 'No especificado'; // Generalmente es igual al número de serie
    $numeroCilindros = $tarjeta['Cilindraje'] ?? '4';
    $tipoCarroceria = $tarjeta['TipoCarroceria'] ?? 'AUTOMOVIL SEDAN';
    $entidadFederativa = 'QUERETARO';
    $municipio = $tarjeta['Municipio'] ?? 'EL MARQUES';
    $numeroCentro = $tarjeta['NumeroCentroVerificacion'] ?? 'No especificado';
    $nombreCentro = $tarjeta['CentroVerificacionNombre'] ?? 'No especificado';
    $lineaVerificacion = '---Linea:1';
    $tecnicoVerificador = 'VERIFICADOR AUTORIZADO';
    $fechaExpedicion = date('d/m/Y', strtotime($tarjeta['FechaExpedicion']));
    $horaEntrada = date('H:i', strtotime($tarjeta['HoraEntrada']));
    $horaSalida = date('H:i', strtotime($tarjeta['HoraSalida']));
    $motivoVerificacion = $tarjeta['MotivoVerificacion'];
    $semestre = date('n', strtotime($tarjeta['FechaExpedicion'])) <= 6 ? '1' : '2';
    $tipoServicio = 'T.de Pasaje Particular...';
    
    // Calcular vigencia
    $fechaVerificacion = new DateTime($tarjeta['FechaExpedicion']);
    $anoVigencia = $fechaVerificacion->format('Y');
    if ($semestre == '1') {
        $periodoVigencia = "Hasta Julio/Agosto del " . $anoVigencia;
    } else {
        $anoVigencia++;
        $periodoVigencia = "Hasta Enero/Febrero del " . $anoVigencia;
    }
    
    // Generar número de prueba aleatorio para la demostración
    $numeroPrueba = mt_rand(3200000, 3299999);
    
    // Ruta de las imágenes
    $imgPath = '../img/';
    
    // Crear nueva instancia de PDF con tamaño específico
    $pdf = new FPDF('L', 'mm', [215.9, 98]);
    
    // Añadir página
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    
    // Verificar e incluir imágenes
    if (file_exists($imgPath . 'Q2.png')) {
        $pdf->Image($imgPath . 'Q2.png', 0, 0, 215.9, 100);
    }
    
    if (file_exists($imgPath . 'qroescudo.png')) {
        $pdf->Image($imgPath . 'qroescudo.png', 3, 3, 20);
    }
    
    if (file_exists($imgPath . 'qrojuntos.png')) {
        $pdf->Image($imgPath . 'qrojuntos.png', 25, 5, 20);
    }
    
    if (file_exists($imgPath . 'lineaco.png')) {
        $pdf->Image($imgPath . 'lineaco.png', 150, 33, 80);
    }
    
    if (file_exists($imgPath . 'sello.png')) {
        $pdf->Image($imgPath . 'sello.png', 155, 67, 30);
    }
    
    if (file_exists($imgPath . 'dos.png')) {
        $pdf->Image($imgPath . 'dos.png', 185, 70, 25);
    }
    
    // Título y encabezados
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY(60, 5);
    $pdf->Cell(0, 4, 'PROGRAMA ESTATAL DE VERIFICACION VEHICULAR', 0, 1);
    $pdf->SetXY(65, 8);
    $pdf->Cell(0, 4, 'PODER EJECUTIVO DEL ESTADO DE QUERETARO', 0, 1);
    $pdf->SetXY(190, 90);
    $pdf->Cell(0, 4, 'PROPIETARIO', 0, 1);
    
    // Rectángulos blancos
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(155, 3, 61, 11, 'F');
    $pdf->Rect(155, 20, 61, 11, 'F');
    
    // Código QR
    if (file_exists($imgPath . 'codigo.png')) {
        $pdf->Image($imgPath . 'codigo.png', 158, 3.5, 53);
    }
    
    // Folio y vigencia
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(177, 10);
    $pdf->Cell(0, 4, $folioCertificado, 0, 1);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(155, 23.5);
    $pdf->Cell(0, 4, 'Vigencia: ' . $periodoVigencia, 0, 1);
    
    // Encabezados de datos del vehículo
    $pdf->SetFont('Arial', '', 4.7);
    $pdf->SetXY(5, 18);
    $pdf->Cell(0, 4, 'DATOS DEL VEHICULO', 0, 1);
    $pdf->SetXY(5, 28.5);
    $pdf->Cell(0, 4, 'TIPO DE SERVICIO', 0, 1);
    $pdf->SetXY(39, 28.5);
    $pdf->Cell(0, 4, 'MARCA', 0, 1);
    $pdf->SetXY(68, 28.5);
    $pdf->Cell(0, 4, 'SUBMARCA', 0, 1);
    $pdf->SetXY(95, 28.5);
    $pdf->Cell(0, 4, 'AÑO/MODELO', 0, 1);
    $pdf->SetXY(128, 28.5);
    $pdf->Cell(0, 4, 'PLACAS', 0, 1);
    $pdf->SetXY(5, 43);
    $pdf->Cell(0, 4, 'NUMERO DE SERIE', 0, 1);
    $pdf->SetXY(39, 43);
    $pdf->Cell(0, 4, 'CLASE', 0, 1);
    $pdf->SetXY(68, 43);
    $pdf->Cell(0, 4, 'TIPO DE COMBUSTIBLE', 0, 1);
    $pdf->SetXY(110, 43);
    $pdf->Cell(0, 4, 'No.IDENTIFICACION VEHICULAR(NIV)', 0, 1);
    $pdf->SetXY(5, 57);
    $pdf->Cell(0, 4, 'NUMERO DE CILINDROS', 0, 1);
    $pdf->SetXY(38, 57);
    $pdf->Cell(0, 4, 'TIPO DE CARROCERIA', 0, 1);
    $pdf->SetXY(72, 57);
    $pdf->Cell(0, 4, 'ENTIDAD FEDERATIVA', 0, 1);
    $pdf->SetXY(120, 57);
    $pdf->Cell(0, 4, 'MUNICIPIO', 0, 1);
    $pdf->SetXY(5, 62);
    $pdf->Cell(0, 4, 'No. DE CENTRO', 0, 1);
    $pdf->SetXY(5, 66);
    $pdf->Cell(0, 4, 'No.DE LINEA DE VERIFICACION', 0, 1);
    $pdf->SetXY(5, 70);
    $pdf->Cell(0, 4, 'TECNICO VERIFICADOR', 0, 1);
    $pdf->SetXY(5, 74);
    $pdf->Cell(0, 4, 'FECHA DE EXPEDICION', 0, 1);
    $pdf->SetXY(5, 78);
    $pdf->Cell(0, 4, 'HORA DE ENTRADA', 0, 1);
    $pdf->SetXY(5, 82);
    $pdf->Cell(0, 4, 'HORA DE SALIDA', 0, 1);
    $pdf->SetXY(5, 86);
    $pdf->Cell(0, 4, 'MOTIVO DE VERIFICACION', 0, 1);
    $pdf->SetXY(5, 90);
    $pdf->Cell(0, 4, 'FOLIO DE CERTIFICADO', 0, 1);
    $pdf->SetXY(5, 94);
    $pdf->Cell(0, 4, 'SEMESTRE', 0, 1);
    
    // Datos del vehículo
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(5, 24);
    $pdf->Cell(0, 4, $tipoServicio, 0, 1);
    $pdf->SetXY(39, 24);
    $pdf->Cell(0, 4, $marca, 0, 1);
    $pdf->SetXY(66, 24);
    $pdf->Cell(0, 4, substr($submarca, 0, 16) . (strlen($submarca) > 16 ? '...' : ''), 0, 1);
    $pdf->SetXY(97, 24);
    $pdf->Cell(0, 4, $anoModelo, 0, 1);
    $pdf->SetXY(128, 24);
    $pdf->Cell(0, 4, $placas, 0, 1);
    $pdf->SetXY(4, 38);
    $pdf->Cell(0, 4, $numeroSerie, 0, 1);
    $pdf->SetXY(39, 38);
    $pdf->Cell(0, 4, substr($clase, 0, 16) . (strlen($clase) > 16 ? '...' : ''), 0, 1);
    $pdf->SetXY(69, 38);
    $pdf->Cell(0, 4, $tipoCombustible, 0, 1);
    $pdf->SetXY(110, 38);
    $pdf->Cell(0, 4, $niv, 0, 1);
    $pdf->SetXY(5, 52);
    $pdf->Cell(0, 4, $numeroCilindros, 0, 1);
    $pdf->SetXY(38, 52);
    $pdf->Cell(0, 4, $tipoCarroceria, 0, 1);
    $pdf->SetXY(72, 52);
    $pdf->Cell(0, 4, $entidadFederativa, 0, 1);
    $pdf->SetXY(120, 52);
    $pdf->Cell(0, 4, $municipio, 0, 1);
    
    // Información método SDB
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(120, 72);
    $pdf->Cell(0, 4, 'Vehiculo analizado por el ', 0, 1);
    $pdf->SetXY(120, 75);
    $pdf->Cell(0, 4, 'metodo SDB con folio ', 0, 1);
    $pdf->SetXY(120, 78);
    $pdf->Cell(0, 4, 'de prueba: ' . $numeroPrueba, 0, 1);
    
    // Resto de datos
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(39, 62);
    $pdf->Cell(0, 4, $numeroCentro . ' ' . substr($nombreCentro, 0, 20) . (strlen($nombreCentro) > 20 ? '...' : ''), 0, 1);
    $pdf->SetXY(39, 66);
    $pdf->Cell(0, 4, $lineaVerificacion, 0, 1);
    $pdf->SetXY(39, 70);
    $pdf->Cell(0, 4, $tecnicoVerificador, 0, 1);
    $pdf->SetXY(39, 74);
    $pdf->Cell(0, 4, $fechaExpedicion, 0, 1);
    $pdf->SetXY(39, 78);
    $pdf->Cell(0, 4, $horaEntrada, 0, 1);
    $pdf->SetXY(39, 82);
    $pdf->Cell(0, 4, $horaSalida, 0, 1);
    $pdf->SetXY(39, 86);
    $pdf->Cell(0, 4, $motivoVerificacion, 0, 1);
    $pdf->SetXY(39, 90);
    $pdf->Cell(0, 4, $folioCertificado, 0, 1);
    $pdf->SetXY(39, 94);
    $pdf->Cell(0, 4, $semestre, 0, 1);
    
    // Líneas divisorias
    $pdf->Line(5, 28, 150, 28);
    $pdf->Line(5, 42, 150, 42);
    $pdf->Line(5, 56, 150, 56);
    
    // Generar el PDF para descargar
    $pdfFilename = 'Tarjeta_Verificacion_' . $folioCertificado . '.pdf';
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