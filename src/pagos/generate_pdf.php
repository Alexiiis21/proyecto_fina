<?php
// Iniciar buffer de salida
ob_start();

// Incluir archivos necesarios
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

// Obtener datos del pago
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               tc.Placas, tc.FechaExpedicion as TC_FechaExpedicion, tc.FechaVencimiento as TC_FechaVencimiento,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor, v.TipoCombustible, 
               v.Cilindraje, v.Transmision,
               pr.Nombre as PropietarioNombre, pr.RFC
        FROM pagos p
        LEFT JOIN tarjetascirculacion tc ON p.ID_Tarjeta_Circulacion = tc.ID_Tarjeta_Circulacion
        LEFT JOIN vehiculos v ON tc.ID_Vehiculo = v.ID_Vehiculo
        LEFT JOIN propietarios pr ON tc.ID_Propietario = pr.ID_Propietario
        WHERE p.ID_Pago = ?
    ");
    $stmt->execute([$id]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pago) {
        http_response_code(404); // Not Found
        echo "<h1>Error: Pago no encontrado</h1>";
        echo "<p>El pago solicitado no existe.</p>";
        echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo "<h1>Error de base de datos</h1>";
    echo "<p>Error al cargar los datos del pago: " . htmlspecialchars($e->getMessage()) . "</p>";
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
    require($fpdfPath);
    
    // Preparar datos para el PDF
    $numeroTransaccion = $pago['NumeroTransaccion'];
    $lineaCaptura = $pago['LineaCaptura'] ?? 'No disponible';
    $fechaLimite = !empty($pago['FechaLimite']) ? date('d-m-Y', strtotime($pago['FechaLimite'])) : 'No disponible';
    $fechaPago = !empty($pago['FechaPago']) ? date('d-m-Y', strtotime($pago['FechaPago'])) : date('d-m-Y');
    $hora = !empty($pago['FechaPago']) ? date('H:i', strtotime($pago['FechaPago'])) : date('H:i');
    $importe = number_format($pago['Importe'], 2);
    
    // Datos del vehículo
    $placas = $pago['Placas'] ?? 'No disponible';
    $marca = $pago['Marca'] ?? 'No disponible';
    $modelo = $pago['Modelo'] ?? 'No disponible';
    $ano = $pago['AnoFabricacion'] ?? 'No disponible';
    $color = $pago['Color'] ?? 'No disponible';
    $combustible = $pago['TipoCombustible'] ?? 'No disponible';
    $transmision = $pago['Transmision'] ?? 'No disponible';
    $cilindraje = $pago['Cilindraje'] ?? 'No disponible';
    
    // Descripción del vehículo
    $vehiculoCompleto = strtoupper("$marca, $modelo, $modelo, $cilindraje HP, $transmision, $combustible");
    $vehiculoColor = strtoupper("MODELO $ano, COLOR $color");
    
    // Ruta de las imágenes
    $imgPath = '../img/';
    $codigoBarraImg = 'codigobarra.png';
    
    // Crear nueva instancia de PDF
    $pdf = new FPDF('L', 'mm', [215.9, 85]);
    
    // Añadir página
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    
    // Rectángulos y colores de fondo
    $pdf->SetFillColor(120, 120, 120);
    $pdf->Rect(5, 5, 10, 20, 'F');
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Rect(15, 5, 2, 20, 'F');
    
    // Título principal
    $pdf->SetFont('Arial', 'B', 35);
    $pdf->SetXY(115, 11);
    $pdf->Cell(0, 4, 'RECAUDANET', 0, 1);
    
    // Barra gris
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Rect(5, 30, 205, 3.5, 'F');
    
    // Información de encabezado
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(5, 26);
    $pdf->Cell(0, 4, 'Poder Ejecutivo del Estado de Queretaro ', 0, 1);
    
    // Texto sobre barra gris
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetXY(5, 30);
    $pdf->Cell(0, 4, 'Gracias por usar Recaudanet', 0, 1);
    
    // Restablecer color de texto
    $pdf->SetTextColor(0, 0, 0);
    
    // Datos del vehículo
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(5, 34);
    $pdf->Cell(0, 4, $placas, 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(5, 37);
    $pdf->Cell(0, 4, $vehiculoColor, 0, 1);
    
    $pdf->SetXY(5, 40);
    $pdf->Cell(0, 4, $vehiculoCompleto, 0, 1);
    
    // Datos del pago - Etiquetas
    $pdf->SetXY(36, 44);
    $pdf->Cell(0, 4, 'Transaccion', 0, 1);
    
    $pdf->SetXY(16.7, 47);
    $pdf->Cell(0, 4, 'Linea de captura para el pago', 0, 1);
    
    $pdf->SetXY(21.8, 50);
    $pdf->Cell(0, 4, 'Fecha limite para el pago', 0, 1);
    
    $pdf->SetXY(41.2, 53);
    $pdf->Cell(0, 4, 'Importe', 0, 1);
    
    // Datos del pago - Valores
    $pdf->SetXY(53, 53);
    $pdf->Cell(0, 4, $importe, 0, 1);
    
    $pdf->SetXY(53, 50);
    $pdf->Cell(0, 4, $fechaLimite, 0, 1);
    
    $pdf->SetXY(53, 47);
    $pdf->Cell(0, 4, $lineaCaptura, 0, 1);
    
    // Nota importante
    $pdf->SetXY(5, 56.5);
    $pdf->Cell(0, 4, 'Nota: El nombre y/o razon social que saldra en el recibo de pago y/o CFDI, sera el registrado en el padron vehicular, el cual una vez pagado no podra ser modificado', 0, 1);
    
    // Datos adicionales - Etiquetas
    $pdf->SetXY(130, 44);
    $pdf->Cell(0, 4, 'Tipo de instrumento de pago', 0, 1);
    
    $pdf->SetXY(147.2, 47);
    $pdf->Cell(0, 4, 'Fecha actual', 0, 1);
    
    $pdf->SetXY(156, 50);
    $pdf->Cell(0, 4, 'Hora', 0, 1);
    
    // Datos adicionales - Valores
    $pdf->SetXY(165, 50);
    $pdf->Cell(0, 4, $hora, 0, 1);
    
    $pdf->SetXY(165, 47);
    $pdf->Cell(0, 4, $fechaPago, 0, 1);
    
    $pdf->SetXY(165, 44);
    $pdf->Cell(0, 4, 'Pago Referenciado', 0, 1);
    
    $pdf->SetXY(100, 81);
    $pdf->Cell(0, 4, $lineaCaptura, 0, 1);
    
    // Número de transacción en negrita
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(53, 44);
    $pdf->Cell(0, 4, substr($numeroTransaccion, 0, 12), 0, 1);
    
    // Texto de bancos
    $pdf->SetXY(56, 65);
    $pdf->Cell(0, 4, 'BANCOS Y ESTABLECIMIENTOS DONDE PUEDES EFECTUAR TUS PAGOS', 0, 1);
    
    // Área de código de barras
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Rect(5, 69, 205, 3.5, 'DF');
    $pdf->Rect(5, 69, 205, 15.5, 'D');
    
    // Texto sobre área de código de barras
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetXY(100, 69);
    $pdf->Cell(0, 4, 'LINEA DE CAPTURA', 0, 1);
    
    // Añadir código de barras si existe
    $codigoBarraPath = $imgPath . $codigoBarraImg;
    if (file_exists($codigoBarraPath)) {
        $pdf->Image($codigoBarraPath, 95, 73, 35);
    }
    
    // Generar el PDF para descargar
    $pdfFilename = 'Recibo_Pago_' . $numeroTransaccion . '.pdf';
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