<?php
// === download_boleto.php ===
// Script encargado de generar un boleto de abordaje con diseño premium y jerarquía clara.

// 1. **CRÍTICO: SUPRIMIR ADVERTENCIAS DEPRECATED DE PHP**
// Esto es necesario para evitar el error fatal "Some data has already been output" en PHP 8.2+.
error_reporting(E_ALL & ~E_DEPRECATED);

// 2. **IMPORTANTE:** Se asume que la librería FPDF está disponible en el entorno.
require('fpdf/fpdf.php'); 

// 3. Función de codificación UTF-8 alternativa para FPDF (compatible con PHP 8.2+)
function iconv_to_iso($text) {
    if (!is_string($text) || empty($text)) {
        return $text;
    }
    // Convertir de UTF-8 a ISO-8859-1 (necesario para FPDF)
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}

// 4. Validar si al menos el ID de reserva está presente
if (!isset($_GET['id_reserva']) || empty($_GET['id_reserva'])) {
    die("ERROR: Se requiere 'id_reserva'.");
}

$id_reserva = trim($_GET['id_reserva']);
$pasajero_url = trim($_GET['pasajero'] ?? ''); 

// Configuración de archivos
$base_dir = __DIR__ . DIRECTORY_SEPARATOR;
$reservas_file = $base_dir . "reservas.csv";
$vuelos_file = $base_dir . "vuelos.csv";

// Funciones auxiliares
function safe_fgetcsv($handle) { return fgetcsv($handle, 1000, ',', '"', '\\'); }

// --- Carga de reserva
$reserva_data = null; $vuelo_data = null; $headers_reserva = [];
if (file_exists($reservas_file) && is_readable($reservas_file)) {
    $handle = @fopen($reservas_file,'r');
    $headers_reserva = safe_fgetcsv($handle) ?: ['id_reserva','id_vuelo','nombre','apellido_paterno','apellido_materno','correo','telefono','clase','precio_total','fecha_reserva','pdf_filename'];
    while (($row = safe_fgetcsv($handle))!==false) {
        if (count($row)<count($headers_reserva)) $row=array_pad($row,count($headers_reserva),'');
        $r = array_combine($headers_reserva,$row);
        if (trim($r['id_reserva']??'')==$id_reserva) { $reserva_data=$r; break; }
    }
    @fclose($handle);
}
if (!$reserva_data) die("ERROR: No se encontró la reserva.");

$id_vuelo = trim($reserva_data['id_vuelo'] ?? '');
if ($id_vuelo && file_exists($vuelos_file)) {
    $handle = @fopen($vuelos_file,'r');
    $headers_vuelo = safe_fgetcsv($handle) ?: ['id_vuelo','aerolinea','origen','destino','fecha_salida','hora_salida','duracion','precio_normal','precio_business','precio_primera'];
    while (($row=safe_fgetcsv($handle))!==false){
        if (count($row)<count($headers_vuelo)) $row=array_pad($row,count($headers_vuelo),'');
        $v=array_combine($headers_vuelo,$row);
        if(trim($v['id_vuelo']??'')==$id_vuelo){$vuelo_data=$v; break;}
    }
    @fclose($handle);
}

// Nombre pasajero
$pasajero_raw = $pasajero_url ?: trim(($reserva_data['nombre']??'').' '.($reserva_data['apellido_paterno']??'').' '.($reserva_data['apellido_materno']??''));
$pasajero = $pasajero_raw ?: "Pasajero: $id_reserva";

// Datos vuelo y simulados
// --- Definición de nombres completos (Para mostrar en la ruta principal)
$origen_completo = strtoupper($vuelo_data['origen'] ?? 'ORIGEN');
$destino_completo = strtoupper($vuelo_data['destino'] ?? 'DESTINO');

// --- Definición de códigos IATA (Para mostrar en el talón)
$origen_code = strtoupper(substr($vuelo_data['origen'] ?? 'NA',0,3));
$destino_code = strtoupper(substr($vuelo_data['destino'] ?? 'NA',0,3));

$fecha_salida = $vuelo_data['fecha_salida'] ?? 'N/A';
$hora_salida = $vuelo_data['hora_salida'] ?? 'N/A';
$aerolinea = $vuelo_data['aerolinea'] ?? 'AEROTEC';
$clase_raw = $reserva_data['clase'] ?? 'N/A';
$clase = ucfirst(strtolower($clase_raw));
$seat = '15F'; // Simulado
$gate = 'B42'; // Simulado
$boarding_time = '08:00'; // Simulado
$class_code = substr(strtoupper($clase), 0, 3);
$id_vuelo = $reserva_data['id_vuelo'] ?? 'AM-101'; // Usar un valor de ejemplo si no hay

// --- Aplicar iconv_to_iso a las variables con acentos/ñ y las etiquetas ---
$pasajero = iconv_to_iso($pasajero);
$origen_completo = iconv_to_iso($origen_completo);
$destino_completo = iconv_to_iso($destino_completo);
$aerolinea = iconv_to_iso($aerolinea); 


$output_filename="Boleto_Abordaje_{$id_reserva}.pdf";

// --- PDF ---
$pdf=new FPDF('L','mm',[100,200]); 
$pdf->AddPage(); 
$pdf->SetAutoPageBreak(false);

// Colores
$PURPLE=[106,13,173]; 
$LIGHT_GRAY=[245,245,245]; // Fondo de datos clave
$GRAY=[120,120,120]; 
$DARK_GRAY=[50,50,50]; 
$WHITE=[255,255,255];

// Dimensiones
$W=190; $H=90; $MarginX=5; $MarginY=5; 
$TICKET_SPLIT=140; // División para el talón (Principal)
$HeaderH=10; // Altura del encabezado morado
$StartX=$MarginX+2; // Posición X inicial

// Fondo y borde
$pdf->SetFillColor(...$WHITE);
$pdf->Rect($MarginX,$MarginY,$W,$H,'F');
$pdf->SetDrawColor(...$PURPLE); $pdf->SetLineWidth(0.5);
$pdf->Rect($MarginX,$MarginY,$W,$H);

// Línea punteada talón
$pdf->SetDrawColor(...$GRAY); $pdf->SetLineWidth(0.2);
for($y=$MarginY;$y<$MarginY+$H;$y+=4){$pdf->Line($MarginX+$TICKET_SPLIT,$y,$MarginX+$TICKET_SPLIT,$y+2);}

// === Encabezados (Barra Superior) ===
$pdf->SetFillColor(...$PURPLE); 
$pdf->Rect($MarginX,$MarginY,$W,$HeaderH,'F'); // Barra morada completa

$pdf->SetTextColor(255,255,255); 
$pdf->SetFont('Arial','B',12);
$pdf->SetXY($MarginX+2,$MarginY+2); 
$pdf->Cell(30,6,strtoupper($aerolinea),0,0,'L');
$pdf->SetFont('Arial','',10); 
$pdf->Cell($W-37,6,iconv_to_iso('PASE DE ABORDAJE / TALÓN'),0,1,'R');


// Reiniciar posición Y después del header
$Y=$MarginY+$HeaderH+4; 
$Y_Stub = $Y;
$BlockH = 15; // Altura de los bloques de datos clave

// === BLOQUE PRINCIPAL (Izquierda) ===

// 1. PASAJERO y ID RESERVA
$colW_info = ($TICKET_SPLIT - $MarginX - 4) / 2; // Ancho para 2 columnas

$pdf->SetY($Y); $pdf->SetX($StartX);
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(...$GRAY);
$pdf->Cell($colW_info,4,iconv_to_iso('PASAJERO'),0,0); $pdf->Cell($colW_info,4,iconv_to_iso('ID RESERVA'),0,1);

$pdf->SetFont('Arial','B',11); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetX($StartX); $pdf->Cell($colW_info,6,$pasajero,0,0);
$pdf->SetTextColor(...$PURPLE); $pdf->Cell($colW_info,6,$id_reserva,0,1);

$Y=$pdf->GetY()+5;

// 2. RUTA PRINCIPAL (Grande - Usa NOMBRES COMPLETOS)
$pdf->SetY($Y); $pdf->SetX($StartX);

// Origen (Nombre completo)
$pdf->SetFont('Arial','B',24); // Reducir un poco el tamaño para nombres largos
$pdf->SetTextColor(...$PURPLE);
$pdf->Cell(45,15,$origen_completo,0,0,'L');

// Flecha/Separador
$pdf->SetFont('Arial','',24); $pdf->SetTextColor(...$GRAY); 
$pdf->Cell(10,15,'>',0,0,'C');

// Destino (Nombre completo)
$pdf->SetFont('Arial','B',24); 
$pdf->SetTextColor(...$PURPLE); 
$pdf->Cell(45,15,$destino_completo,0,1,'L');

$Y=$pdf->GetY()+5;

// 3. INFORMACIÓN CRÍTICA (ASIENTO, PUERTA, VUELO, CLASE) - Bloques individuales
$dataW = ($TICKET_SPLIT - $MarginX - 4) / 4; // Ancho para 4 bloques
$currentX = $StartX;

// --- ASISTENTO ---
$pdf->SetFillColor(...$LIGHT_GRAY); 
$pdf->SetDrawColor(...$PURPLE); 
$pdf->Rect($currentX, $Y, $dataW, $BlockH, 'FD'); // Fondo y borde

$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetXY($currentX, $Y + 2); $pdf->Cell($dataW, 3, iconv_to_iso('ASIENTO'), 0, 0, 'C'); 
$pdf->SetFont('Arial','B', 18); $pdf->SetTextColor(...$PURPLE);
$pdf->SetXY($currentX, $Y + 6); $pdf->Cell($dataW, 7, $seat, 0, 0, 'C'); 
$currentX += $dataW;

// --- PUERTA ---
$pdf->Rect($currentX, $Y, $dataW, $BlockH, 'FD');
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetXY($currentX, $Y + 2); $pdf->Cell($dataW, 3, iconv_to_iso('PUERTA'), 0, 0, 'C'); 
$pdf->SetFont('Arial','B', 18); $pdf->SetTextColor(...$PURPLE);
$pdf->SetXY($currentX, $Y + 6); $pdf->Cell($dataW, 7, $gate, 0, 0, 'C'); 
$currentX += $dataW;

// --- VUELO ---
$pdf->Rect($currentX, $Y, $dataW, $BlockH, 'FD');
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetXY($currentX, $Y + 2); $pdf->Cell($dataW, 3, iconv_to_iso('VUELO'), 0, 0, 'C'); 
$pdf->SetFont('Arial','B', 14); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetXY($currentX, $Y + 6); $pdf->Cell($dataW, 7, $id_vuelo, 0, 0, 'C'); 
$currentX += $dataW;

// --- CLASE ---
$pdf->Rect($currentX, $Y, $dataW, $BlockH, 'FD');
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetXY($currentX, $Y + 2); $pdf->Cell($dataW, 3, iconv_to_iso('CLASE'), 0, 0, 'C'); 
$pdf->SetFont('Arial','B', 14); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetXY($currentX, $Y + 6); $pdf->Cell($dataW, 7, $class_code, 0, 0, 'C'); 
$currentX += $dataW;

$Y=$pdf->GetY() + $BlockH + 2;

// 4. FECHA y HORA DE ABORDAJE (Debajo del Vuelo)
$pdf->SetY($Y); $pdf->SetX($StartX);

// Títulos
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->Cell(30, 4, iconv_to_iso('FECHA'), 0, 0, 'L'); 
$pdf->Cell(30, 4, iconv_to_iso('HORA ABORDAJE'), 0, 1, 'L'); 

// Valores
$pdf->SetFont('Arial','B',14); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetX($StartX);
$pdf->Cell(30, 6, $fecha_salida, 0, 0, 'L');
$pdf->SetTextColor(...$PURPLE);
$pdf->Cell(30, 6, $boarding_time, 0, 1, 'L');


// === BLOQUE TALÓN (Derecha - USA CÓDIGOS IATA) ===
$StubX = $MarginX + $TICKET_SPLIT;
$StubW = $W - $TICKET_SPLIT - $MarginX / 2;
$X_Stub = $StubX + 2;

$Y_Stub=$MarginY+$HeaderH+4;

// 1. RUTA TALÓN
$pdf->SetY($Y_Stub); $pdf->SetX($X_Stub);
$pdf->SetFont('Arial','',8); $pdf->SetTextColor(...$GRAY);
$pdf->Cell($StubW,4,iconv_to_iso('RUTA'),0,1,'L');
$pdf->SetFont('Arial','B',12); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW,5,$origen_code.' -> '.$destino_code,0,1,'L');

$pdf->Ln(3);

// 2. VUELO Y FECHA TALÓN
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW/2, 4, iconv_to_iso('VUELO'), 0, 0, 'L');
$pdf->Cell($StubW/2, 4, iconv_to_iso('FECHA'), 0, 1, 'L');

$pdf->SetFont('Arial','B',9); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW/2, 4, $id_vuelo . ' (' . $class_code . ')', 0, 0, 'L');
$pdf->Cell($StubW/2, 4, $fecha_salida, 0, 1, 'L');

$pdf->Ln(2);

// 3. ASIENTO y PUERTA TALÓN
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW/2, 4, iconv_to_iso('ASIENTO'), 0, 0, 'L');
$pdf->Cell($StubW/2, 4, iconv_to_iso('PUERTA'), 0, 1, 'L');

$pdf->SetFont('Arial','B',16); $pdf->SetTextColor(...$PURPLE);
$pdf->SetX($X_Stub); $pdf->Cell($StubW/2, 6, $seat, 0, 0, 'L');
$pdf->Cell($StubW/2, 6, $gate, 0, 1, 'L');

$pdf->Ln(2);

// 4. PASAJERO TALÓN
$pdf->SetFont('Arial','',7); $pdf->SetTextColor(...$GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW,4,iconv_to_iso('PASAJERO'),0,1,'L');
$pdf->SetFont('Arial','B',8); $pdf->SetTextColor(...$DARK_GRAY);
$pdf->SetX($X_Stub); $pdf->Cell($StubW,4,$pasajero,0,1,'L');


// 8. Output del PDF
$pdf->Output('D',$output_filename);
exit;
?>