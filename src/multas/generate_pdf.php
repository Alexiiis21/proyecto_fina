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
               l.NumeroLicencia, l.TipoLicencia,
               c.Nombre as NombreConductor, c.CURP, c.RFC, c.Telefono, c.CorreoElectronico, 
               o.Nombre as NombreOficial, o.NumeroIdentificacion as NumeroIdentificacionOficial, o.Cargo,
               tc.Placas, tc.FechaExpedicion as FechaExpedicionTC,
               v.Marca, v.Modelo, v.AnoFabricacion, v.Color, v.NumeroSerie, v.NumeroMotor, 
               v.TipoCarroceria, v.NumeroAsientos, v.Cilindraje, v.TipoCombustible, v.Uso, v.Transmision, v.NumeroPuertas, v.Clase,
               d.Calle, d.NumeroExterior, d.NumeroInterior, d.Colonia, d.Municipio, d.Estado, d.CodigoPostal
        FROM multas m
        LEFT JOIN licencias l ON m.ID_Licencia = l.ID_Licencia
        LEFT JOIN conductores c ON l.ID_Conductor = c.ID_Conductor
        LEFT JOIN domicilios d ON c.ID_Domicilio = d.ID_Domicilio
        LEFT JOIN oficiales o ON m.ID_Oficial = o.ID_Oficial
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
        SELECT COUNT(*) FROM pagos 
        WHERE ID_Multa = ?
    ");
    $stmt->execute([$id]);
    $tienePago = ($stmt->fetchColumn() > 0);
} catch (PDOException $e) {
    $tienePago = false;
}

require('../vendor/fpdf/fpdf.php');

// Función para reemplazar utf8_decode
function convertToLatin1($text) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
}

class BoletaInfraccionPDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', '', 5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 2.5, convertToLatin1('Con fundamento en los artículos 21 fracción V de la Ley de Tránsito para el Estado de Querétaro y 8 fracción IX, inciso a) del'), 0, 1, 'C');
        $this->Cell(0, 2.5, convertToLatin1('Reglamento de la Ley de Tránsito para el Estado de Querétaro, se levanta la presente boleta de infracción.'), 0, 1, 'C');
        $this->Ln(1);
    }

    function Footer() {
        $this->SetY(-50);
        $this->SetFont('Arial', '', 4.5);
        $this->SetTextColor(0, 0, 0);
        $texto = "Para el pago de la presente infracción y devolución de la(s) garantía(s) retenida(s), es aplicable lo dispuesto en el artículo 179 del Reglamento de la Ley de Tránsito para el Estado de Querétaro.\n\nEl pago de la multa se puede realizar en:\n1. Oficinas recaudadoras de la Dirección de Ingresos de la Secretaría de Planeación y Finanzas del Poder Ejecutivo del Estado de Querétaro, y\n2. Centros autorizados para este fin, incluyendo medios electrónicos de pago.\n\nLas tarjetas de circulación o documentación retenida serán devueltas en las oficinas de la Secretaría de Seguridad Ciudadana, una vez realizado el pago.\n\nEl infractor tendrá un plazo de noventa días naturales contados a partir de la fecha de emisión de la boleta de infracción para realizar el pago, el cual será sujeto a descuento del 50% dentro de los diez primeros días hábiles.\n\nSiempre y cuando no se trate de las infracciones previstas en el artículo 179 bis del Reglamento de la Ley de Tránsito para el Estado de Querétaro, vencido el plazo señalado sin que se realice el pago, deberá cubrir las demás cargos fiscales que establezca el Código Fiscal del Estado de Querétaro.\n\nEl infractor podrá solicitar la devolución de su garantía al día hábil siguiente al de la emisión de la boleta de infracción. La persona que considere que la infracción fue indebida por parte del personal operativo, puede presentar un recurso al teléfono 442 309 1400, extensión 10236, realizar una denuncia por la App Denuncia Ciudadana, enviar un correo electrónico a denuncia@queretaro.gob.mx o dirigirse a las oficinas de Asuntos Internos de la Secretaría de Seguridad Ciudadana en días y horas hábiles.\n\nSe hace del conocimiento del infractor que, de conformidad con el artículo 8 fracción XII de la Ley de Procedimientos Administrativos del Estado de Querétaro, todo acto administrativo es recurrible mediante recurso de revisión o juicio de nulidad, en términos de las disposiciones jurídicas aplicables.";
        $this->MultiCell(90, 2, convertToLatin1($texto), 0, 'J');
        $this->SetY(-8);
        $this->SetFont('Arial', 'B', 6);
        $this->Cell(45, 3, convertToLatin1('POLICÍA ESTATAL'), 0, 0, 'L');
        $this->SetX(60);
        $this->Cell(35, 3, '089 SIEM', 0, 1, 'R');
        $this->SetFont('Arial', '', 5);
        $this->Cell(60, 2, convertToLatin1('Secretaría de Seguridad Ciudadana del Estado de Querétaro'), 0, 0, 'L');
        $this->SetX(60);
        $this->Cell(35, 2, convertToLatin1('Programa de Combate de Marcas'), 0, 1, 'R');
        $this->Cell(45, 2, 'Emergencias: 911', 0, 0, 'L');
        $this->SetX(60);
        $this->Cell(35, 2, '442 309 14 00', 0, 0, 'R');
    }

    function DrawBox($x, $y, $w, $h, $label = '', $content = '') {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.15);
        $this->Rect($x, $y, $w, $h);

        if ($label) {
            $this->SetFillColor(230, 230, 230);
            $this->SetXY($x + 0.7, $y + 0.3);
            $this->SetFont('Arial', '', 4.5);
            $labelWidth = $this->GetStringWidth(convertToLatin1($label)) + 1.5;
            if ($labelWidth > $w - 1.5) $labelWidth = $w - 1.5;
            $this->Cell($labelWidth, 2.5, convertToLatin1($label), 0, 0, 'L', true);
        }

        if ($content) {
            $this->SetFont('Arial', 'B', 6);
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($x + 0.7, $y + 2.5);
            $this->Cell($w - 1.5, 3, convertToLatin1($content), 0, 0, 'L');
        }
    }
}

$fechaMulta = new DateTime($multa['Fecha']);
$dia = $fechaMulta->format('d');
$mes = $fechaMulta->format('m');
$ano = $fechaMulta->format('Y');
$hora = $fechaMulta->format('H:i');

$domicilio = '';
if (!empty($multa['Calle'])) {
    $domicilio .= $multa['Calle'];
    if (!empty($multa['NumeroExterior'])) {
        $domicilio .= ' #' . $multa['NumeroExterior'];
    }
    if (!empty($multa['NumeroInterior'])) {
        $domicilio .= ', Int. ' . $multa['NumeroInterior'];
    }
    if (!empty($multa['Colonia'])) {
        $domicilio .= ', ' . $multa['Colonia'];
    }
    if (!empty($multa['Municipio'])) {
        $domicilio .= ', ' . $multa['Municipio'];
    }
    if (!empty($multa['Estado'])) {
        $domicilio .= ', ' . $multa['Estado'];
    }
    if (!empty($multa['CodigoPostal'])) {
        $domicilio .= ' CP. ' . $multa['CodigoPostal'];
    }
}

$pdf = new BoletaInfraccionPDF('P', 'mm', array(95, 210));
$pdf->AddPage();

// Sección 1: Datos principales
$y = 8;
$pdf->DrawBox(4, $y, 10, 7, 'Día', $dia);
$pdf->DrawBox(15, $y, 10, 7, 'Mes', $mes);
$pdf->DrawBox(26, $y, 13, 7, 'Año', $ano);
$pdf->DrawBox(40, $y, 15, 7, 'Hora', $hora);
$pdf->DrawBox(56, $y, 35, 7, 'Folio', $multa['ID_Multa']);

$y += 8;
$pdf->DrawBox(4, $y, 42, 7, 'Aplicación', 'Sistema de Multas');
$pdf->DrawBox(47, $y, 44, 7, 'Ubicación', $multa['Municipio'] ?? 'No especificado');

$y += 8;
$pdf->DrawBox(4, $y, 42, 7, 'Referencia', 'Ref: ' . $multa['ID_Multa']);
$pdf->DrawBox(47, $y, 44, 7, 'Municipio', $multa['Municipio'] ?? 'No especificado');

// Sección 2: Conductor
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('CONDUCTOR'), 0, 1, 'L');

$y += 4;
$nombreCompleto = explode(' ', $multa['NombreConductor'] ?? 'No especificado');
$nombre = (count($nombreCompleto) > 1) ? $nombreCompleto[0] : $multa['NombreConductor'];
$apellido = (count($nombreCompleto) > 1) ? implode(' ', array_slice($nombreCompleto, 1)) : '';

$pdf->DrawBox(4, $y, 30, 7, 'Nombre', $nombre);
$pdf->DrawBox(35, $y, 30, 7, 'Apellido', $apellido);
$pdf->DrawBox(66, $y, 25, 7, 'CURP', $multa['CURP'] ?? '');

$y += 7;
$pdf->DrawBox(4, $y, 87, 7, 'Domicilio', $domicilio ? $domicilio : 'No especificado');

// Sección 3: Vehículo
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('VEHÍCULO'), 0, 1, 'L');

$y += 4;
$pdf->DrawBox(4, $y, 13, 7, 'Tipo', $multa['TipoCarroceria'] ?? '');
$pdf->DrawBox(18, $y, 13, 7, 'Marca', $multa['Marca'] ?? '');
$pdf->DrawBox(32, $y, 13, 7, 'Línea', $multa['Modelo'] ?? '');
$pdf->DrawBox(46, $y, 13, 7, 'Año', $multa['AnoFabricacion'] ?? '');
$pdf->DrawBox(60, $y, 13, 7, 'Color', $multa['Color'] ?? '');
$pdf->DrawBox(74, $y, 17, 7, 'Placas', $multa['Placas'] ?? '');

$y += 7;
$pdf->DrawBox(4, $y, 44, 7, 'NIV', $multa['NumeroSerie'] ?? '');
$pdf->DrawBox(49, $y, 21, 7, 'Entidad', $multa['Estado'] ?? '');
$pdf->DrawBox(71, $y, 20, 7, 'Propietario', '');

// Sección 4: Infracción
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('INFRACCIÓN'), 0, 1, 'L');

$y += 4;
$pdf->DrawBox(4, $y, 87, 10, 'MOTIVO', $multa['Motivo']);

// Sección 5: Documentos retenidos
$y += 11;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('DOCUMENTOS RETENIDOS'), 0, 1, 'L');

$y += 4;
$pdf->DrawBox(4, $y, 20, 7, 'Licencia', '□');
$pdf->DrawBox(25, $y, 25, 7, 'Tarjeta de Circulación', '□');
$pdf->DrawBox(51, $y, 20, 7, 'Placas', '□');
$pdf->DrawBox(72, $y, 19, 7, 'Vehículo', '□');

// Sección 6: Datos del oficial
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('DATOS DEL OFICIAL'), 0, 1, 'L');

$y += 4;
$pdf->DrawBox(4, $y, 40, 7, 'Nombre', $multa['NombreOficial'] ?? '');
$pdf->DrawBox(45, $y, 15, 7, 'ID', $multa['NumeroIdentificacionOficial'] ?? '');
$pdf->DrawBox(61, $y, 15, 7, 'Cargo', $multa['Cargo'] ?? '');
$pdf->DrawBox(77, $y, 14, 7, 'Firma', '');

// Sección 7: Lugar para aclaraciones
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('LUGAR PARA ACLARACIONES Y PAGO'), 0, 1, 'L');

$y += 4;
$pdf->DrawBox(4, $y, 87, 7, 'Dirección', 'Oficinas de Tránsito Municipal');

$y += 7;
$pdf->DrawBox(4, $y, 87, 7, 'Horario', 'Lunes - Viernes de 08:30 - 14:30 hrs');

// Sección 8: Estado de pago
$y += 9;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('ESTADO DE PAGO'), 0, 1, 'L');
$y += 4;
$pdf->DrawBox(4, $y, 87, 7, '', $tienePago ? 'PAGADO' : 'PENDIENTE');

// Sección 9: Importe
$y += 8;
$pdf->SetFont('Arial', 'B', 5.5);
$pdf->SetXY(4, $y);
$pdf->Cell(0, 4, convertToLatin1('IMPORTE'), 0, 1, 'L');
$y += 4;
$pdf->DrawBox(4, $y, 87, 7, '', '$ ' . number_format($multa['Importe'], 2) . ' MXN');

// Nombre del archivo
$filename = 'Multa_' . $id . '_' . date('Ymd') . '.pdf';

// Salida del PDF
$pdf->Output('I', $filename);
?>