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
    echo "<p>ID de licencia no válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver atrás</a></p>";
    exit;
}

$id = (int)$_GET['id'];

// Obtener datos de la licencia
try {
    // Primero obtenemos información básica de la licencia
    $stmt = $pdo->prepare("
        SELECT * FROM licencias WHERE ID_Licencia = ?
    ");
    $stmt->execute([$id]);
    $licencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licencia) {
        http_response_code(404); // Not Found
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
    if (!empty($licencia['ID_Conductor'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               c.ImagenPerfil as Foto,  
               c.Firma 
        FROM conductores c 
        WHERE c.ID_Conductor = ?
    ");
    $stmt->execute([$licencia['ID_Conductor']]);
    $conductor = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $conductor = [];
}
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo "<h1>Error de base de datos</h1>";
    echo "<p>Error al cargar los datos de la licencia: " . htmlspecialchars($e->getMessage()) . "</p>";
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
    // Nombre completo del conductor
    $nombreCompleto = $conductor['Nombre'] ?? 'No especificado';
    if (isset($conductor['Apellidos'])) {
        // Dividir apellidos para mostrarlos separados
        $apellidos = explode(' ', $conductor['Apellidos']);
        $apellido1 = $apellidos[0] ?? '';
        $apellido2 = $apellidos[1] ?? '';
    } elseif (isset($conductor['Apellido'])) {
        $apellido1 = $conductor['Apellido'];
        $apellido2 = '';
    } elseif (isset($conductor['ApellidoPaterno'])) {
        $apellido1 = $conductor['ApellidoPaterno'];
        $apellido2 = $conductor['ApellidoMaterno'] ?? '';
    } else {
        $apellido1 = '';
        $apellido2 = '';
    }
    
    // Rutas de las imágenes - ajusta estas rutas según donde tengas guardadas tus imágenes
    $imgPath = '../img/';
    
    // Crear nueva instancia de PDF con tamaño específico
    $pdf = new FPDF('P','mm',[85.6, 53.98]); 
    
    // PRIMERA PÁGINA - FRENTE DE LA LICENCIA
    $pdf->AddPage();
    
    // Imagen de fondo
    if (file_exists($imgPath . 'fondoblanco.jpg')) {
        $pdf->Image($imgPath . 'fondoblanco.jpg', 0, 0, 53.98, 85.6);
    } else {
        // Si no existe la imagen, usar un rectángulo blanco
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, 53.98, 85.6, 'F');
    }
    
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFillColor(255, 255, 255);
    
    // Imágenes y logos del encabezado
    if (file_exists($imgPath . 'escudo.png')) {
        $pdf->Image($imgPath . 'escudo.png', 5, 5, 10);
    }
    
    if (file_exists($imgPath . 'lin1.png')) {
        $pdf->Image($imgPath . 'lin1.png', 10, 5, 13);
    }
    
    // Encabezado de la licencia
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(18, 5);
    $pdf->Cell(0, 4, 'Estados Unidos Mexicanos', 0, 1);
    
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(18, 7);
    $pdf->Cell(0, 4, 'Poder Ejecutivo del Estado de Queretaro', 0, 1);
    
    $pdf->SetFont('Arial', 'B', 5);
    $pdf->SetXY(18, 10);
    $pdf->Cell(0, 4, 'Secretaria de Seguridad Ciudadana', 0, 1);
    
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetXY(18, 12);
    $pdf->Cell(0, 4, 'Licencia para conducir', 0, 1);
    
    // Foto del conductor
    if (!empty($conductor['ImagenPerfil']) && file_exists('../uploads/fotos/' . $conductor['ImagenPerfil'])) {
    $pdf->Image('../uploads/fotos/' . $conductor['ImagenPerfil'], 32, 18, 18, 25);
} elseif (file_exists($imgPath . 'foto23.png')) {
    $pdf->Image($imgPath . 'foto23.png', 32, 18, 18, 25);
}
    
    // Datos personales
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(43.8, 43);
    $pdf->Cell(0, 4, 'Nombre', 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(40, 45);
    $pdf->Cell(0, 4, $apellido1, 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(36.5, 48);
    $pdf->Cell(0, 4, $apellido2, 0, 1);
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(25, 51);
    $pdf->Cell(0, 4, $nombreCompleto, 0, 1);
    
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(41.5, 53);
    $pdf->Cell(0, 4, 'Observaciones', 0, 1);
    
    // Número de licencia
    $pdf->SetFont('Arial', '', 3);
    $pdf->SetXY(23, 30);
    $pdf->Cell(0, 4, 'No.de Licencia', 0, 1);
    
    $pdf->SetFont('Arial', '', 5.9);
    $pdf->SetTextColor(255, 0, 0); // Rojo
    $pdf->SetXY(19, 33);
    $pdf->Cell(0, 4, $licencia['NumeroLicencia'], 0, 1);
    
    $pdf->SetFont('Arial', '', 3);
    $pdf->SetTextColor(0, 0, 0); // Negro
    $pdf->SetXY(21.5, 35.9);
    $pdf->Cell(0, 4, 'AUTOMOVILISTA', 0, 1);
    
    // Fechas y datos de la licencia
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(5, 55);
    $pdf->Cell(30, 3, 'Fecha de Nacimiento', 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(5, 57);
    $pdf->Cell(30, 3, $licencia['FechaNacimiento'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(5, 58.9);
    $pdf->Cell(30, 3, 'Fecha de Expedicion', 0, 1);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(5, 61);
    $pdf->Cell(30, 3, $licencia['FechaExpedicion'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(5, 63);
    $pdf->Cell(30, 3, 'Valida hasta', 0, 1);
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(5, 65);
    $pdf->Cell(30, 3, $licencia['Vigencia'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(5, 67);
    $pdf->Cell(30, 3, 'Antiguedad', 0, 1);
    
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetXY(5, 69);
    $pdf->Cell(30, 3, $licencia['Antiguedad'], 0, 1);
    
    // Tipo de licencia
    $pdf->SetFillColor(255, 255, 170); 
    $pdf->Rect(5, 73, 8, 8, 'F');    
    $pdf->SetTextColor(0, 0, 0);     
    $pdf->SetFont('Arial', 'B', 7);  
    $pdf->SetXY(5, 73);
    $pdf->Cell(8, 8, $licencia['TipoLicencia'], 0, 0, 'C');
    
    // Texto legal
    $pdf->SetFont('Arial', '', 3);
    $pdf->SetXY(18, 75);
    $pdf->Cell(30, 3, 'AUTORIZO PARA QUE LA PRESENTE SEA', 0, 1);
    
    $pdf->SetFont('Arial', '', 3);
    $pdf->SetXY(16.5, 76);
    $pdf->Cell(30, 3, 'RECABADA COMO GARANTIA DE INFRACCION', 0, 1);
    
    $pdf->SetFont('Arial', 'B', 3);
    $pdf->SetXY(27, 63);
    $pdf->Cell(30, 3, 'Firma', 0, 1);
    
    // Firma del conductor
    if (!empty($conductor['Firma']) && file_exists('../uploads/firmas/' . $conductor['Firma'])) {
    $pdf->Image('../uploads/firmas/' . $conductor['Firma'], 22, 65, 17, 5);
} elseif (file_exists($imgPath . 'firma.png')) {
    $pdf->Image($imgPath . 'firma.png', 22, 65, 17, 5);
}
    
    // Imagen del mapa (si existe)
    if (file_exists($imgPath . 'mapa1.jpeg')) {
        $pdf->Image($imgPath . 'mapa1.jpeg', 42, 76, 13, 5);
    }
    
    // SEGUNDA PÁGINA - REVERSO DE LA LICENCIA
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFillColor(245, 245, 245); // Blanco opaco simulado
    $pdf->Rect(0, 0, 53.98, 85.6, 'F');
    
    // Imágenes y logos
    if (file_exists($imgPath . 'emergencia.png')) {
        $pdf->Image($imgPath . 'emergencia.png', 2, 2, 10);
    }
    
    if (file_exists($imgPath . '089.png')) {
        $pdf->Image($imgPath . '089.png', 43, 2, 8);
    }
    
    if (file_exists($imgPath . 'rneg.png')) {
        $pdf->Image($imgPath . 'rneg.png', 14, 0.5, 25);
    }
    
    // Número de folio
    $pdf->SetTextColor(255, 255, 255); // Texto blanco
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetXY(14, 3.5);
    $pdf->Cell(25, 5, 'B' . str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT), 0, 0, 'C');
    
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(43.8, 8);
    $pdf->Cell(0, 4, 'Domicilio', 0, 1);
    
    // Datos de domicilio
    $pdf->SetFont('Arial', '', 5);
    
    // Dirección con partes
    $direccion = $domicilio['Direccion'] ?? 'No especificado';
    $pdf->SetXY(36, 10);
    $pdf->Cell(0, 4, $direccion, 0, 1);
    
    $numExt = $domicilio['NumeroExterior'] ?? 'S/N';
    $pdf->SetXY(47, 12);
    $pdf->Cell(0, 4, $numExt, 0, 1);
    
    $colonia = $domicilio['Colonia'] ?? '';
    $pdf->SetXY(40.5, 14);
    $pdf->Cell(0, 4, $colonia, 0, 1);
    
    $cp = $domicilio['CodigoPostal'] ?? '';
    $pdf->SetXY(41.7, 16);
    $pdf->Cell(0, 4, 'C.P.' . $cp, 0, 1);
    
    $ciudad = $domicilio['Ciudad'] ?? '';
    $pdf->SetXY(38.5, 18);
    $pdf->Cell(0, 4, $ciudad, 0, 1);
    
    // Imagen de autos (si existe)
    if (file_exists($imgPath . 'autos.png')) {
        $pdf->Image($imgPath . 'autos.png', 2, 15, 50);
    }
    
    // Restricciones
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(2, 25);
    $pdf->Cell(0, 4, 'Restricciones', 0, 1);
    
    $restricciones = isset($conductor['Restricciones']) && !empty($conductor['Restricciones']) 
                    ? $conductor['Restricciones'] : 'NINGUNA';
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(2, 27);
    $pdf->Cell(0, 4, '9' . $restricciones, 0, 1);
    
    // Grupo sanguíneo
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(39, 25);
    $pdf->Cell(0, 4, 'Grupo Sanguineo', 0, 1);
    
    $grupoSanguineo = !empty($conductor['GrupoSanguineo']) ? $conductor['GrupoSanguineo'] : 
                      (!empty($licencia['GrupoSanguineo']) ? $licencia['GrupoSanguineo'] : 'No especificado');
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(45.4, 27);
    $pdf->Cell(0, 4, $grupoSanguineo, 0, 1);
    
    // Donador de órganos
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(37, 29);
    $pdf->Cell(0, 4, 'Donador de Organos', 0, 1);
    
    $donador = isset($conductor['DonadorOrganos']) && $conductor['DonadorOrganos'] == 1 ? 'SI' : 'NO';
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(47.5, 31);
    $pdf->Cell(0, 4, $donador, 0, 1);
    
    // Número de emergencia
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(34.8, 33);
    $pdf->Cell(0, 4, 'Numero de Emergencias', 0, 1);
    
    $numEmergencia = !empty($conductor['NumeroEmergencia']) ? $conductor['NumeroEmergencia'] : '000-000-000-0000';
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetXY(36.1, 35);
    $pdf->Cell(0, 4, $numEmergencia, 0, 1);
    
    // Firma del secretario
    if (file_exists($imgPath . 'firma3.png')) {
        $pdf->Image($imgPath . 'firma3.png', 42, 39.5, 8);
    }
    
    // Datos del secretario
    $pdf->SetFont('Arial', '', 3.7);
    $pdf->SetXY(17, 47.5);
    $pdf->Cell(0, 4, 'MTO.EN GPA.MIGUEL ANGEL CONTRERAS ALVAREZ', 0, 1);
    
    $pdf->SetXY(23.5, 49.5);
    $pdf->Cell(0, 4, 'SECRETARIO DE SEGURIDAD CIUDADANA', 0, 1);
    
    // Fundamento legal
    $pdf->SetFont('Arial', 'B', 2.6);
    $pdf->SetXY(1, 51);
    $pdf->Cell(0, 4, 'Fundamento Legal', 0, 1);
    
    $pdf->SetFont('Arial', '', 3.1);
    $pdf->SetXY(1, 53);
    $pdf->Cell(0, 4, 'Articulo19fraccionXIy33fraccionIdelaLeyOrganicadelPoderEjecutivodelEstadodeQueretaro,articulo9', 0, 1);
    
    $pdf->SetXY(1, 54.5);
    $pdf->Cell(0, 4, 'fraccionXIy55delaLeydeTransitodelEstadodeQueretaro,articulo4delaLeydeProcedimientos', 0, 1);
    
    $pdf->SetXY(1, 56);
    $pdf->Cell(0, 4, 'AdministrativodelEstadodeQueretaro,articulo134,135,136,137,138,139,140,141,142y143delReglamentode', 0, 1);
    
    $pdf->SetXY(1, 57.5);
    $pdf->Cell(0, 4, 'TransitodeEstadodeQueretaro,articulo6,fraccionIV,incisoby20,fraccionVdelaLeydelaSecretariade', 0, 1);
    
    $pdf->SetXY(1, 59);
    $pdf->Cell(0, 4, 'SeguridadCiudadana', 0, 1);
    
    // Escudo y logotipos
    if (file_exists($imgPath . 'esqro1.png')) {
        $pdf->Image($imgPath . 'esqro1.png', 27, 70, 12);
    }
    
    $pdf->SetFont('Arial', '', 4);
    $pdf->SetXY(39, 72);
    $pdf->Cell(0, 4, 'SECRETARIA', 0, 1);
    
    $pdf->SetXY(39, 74);
    $pdf->Cell(0, 4, 'DE SEGURIDAD', 0, 1);
    
    $pdf->SetXY(39, 76);
    $pdf->Cell(0, 4, 'CIUDADANA', 0, 1);
    
    if (file_exists($imgPath . 'linea.png')) {
        $pdf->Image($imgPath . 'linea.png', 30, 71, 17);
    }
    
    // Generar el PDF como descarga
    $filename = 'Licencia_' . $licencia['NumeroLicencia'] . '.pdf';
    $pdf->Output('D', $filename);
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