<?php
// procesar_reserva.php - versión final con CSV, FPDF y fix de sesión
// ----------------------------------------------------------
session_start();
date_default_timezone_set('America/Mexico_City');



// Incluir FPDF (Asegúrate de que la ruta sea correcta)
require_once('fpdf/fpdf.php'); 

// Archivos
$reservas_file = __DIR__ . DIRECTORY_SEPARATOR . 'reservas.csv';
$billetes_dir = __DIR__ . DIRECTORY_SEPARATOR . 'billetes';

// Array de errores
$errores = [];
$pdf_filename = '';

// Aceptar POST real (preferible)
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// Si no fue POST, mostrar error claro
if (!$isPost) {
    $errores[] = "El formulario no fue enviado por método POST. Asegúrese de que el formulario use method=\"POST\".";
} else {
    // Recolectar datos
    $id_vuelo = trim($_POST['id_vuelo'] ?? ($_GET['id'] ?? ''));
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $correo = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono'] ?? '');
    $clase = trim($_POST['clase_seleccionada'] ?? ($_POST['clase'] ?? 'normal'));
    $precio_total = floatval(str_replace([',','$'], ['', ''], $_POST['total_a_pagar'] ?? ($_POST['precio_total'] ?? 0)));

    // Validaciones
    if ($id_vuelo === '') { $errores[] = "No se recibió el ID del vuelo (id_vuelo)."; }
    if ($nombre === '') { $errores[] = "El nombre es obligatorio."; }
    
    // VALIDACIÓN DE EMAIL MODIFICADA: Ahora solo verifica que no esté vacío.
    // Esto es para permitir correos como '2585@gmailcom' en entornos de prueba.
    if ($correo === '') { $errores[] = "Correo inválido o vacío."; }
    // if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $errores[] = "Correo inválido o vacío."; } // Línea original
    
    if ($precio_total <= 0) { $errores[] = "Precio total inválido o nulo."; }
    if ($telefono === '') { $errores[] = "Teléfono de contacto requerido."; }

    // Si no hay errores, intentamos escribir el CSV y generar el PDF
    if (empty($errores)) {
        // Preparar id de reserva
        $id_reserva = 'RES-' . date('Ymd') . '-' . uniqid(); 

        $reserva_datos = [
            $id_reserva,
            $id_vuelo,
            $nombre,
            $apellido_paterno,
            $apellido_materno,
            $correo,
            $telefono,
            $clase,
            number_format($precio_total, 2, '.', ''), // precio normalizado
            date('Y-m-d H:i:s')
        ];

        $write_successful = false;
        
        // 1. Guardar CSV
        // El @ silencia advertencias en caso de fallo, que se maneja con la verificación del handle
        $handle = @fopen($reservas_file, 'a'); 
        if ($handle !== false) {
            $writeOk = fputcsv($handle, $reserva_datos, ',', '"', '\\');
            fclose($handle);

            if ($writeOk !== false) {
                $write_successful = true; // Escritura CSV confirmada
            } else {
                $errores[] = "Error de Escritura (fputcsv): El sistema no escribió la línea en el CSV.";
            }
        } else {
            $errores[] = "Error al abrir el archivo: No se pudo abrir/crear el archivo de reservas en: " . htmlspecialchars($reservas_file);
        }

        // 2. Generar PDF si el CSV fue guardado
        if ($write_successful) {
            
            // Comprobación y creación de directorio de billetes
            if (!is_dir($billetes_dir)) {
                // El @ silencia advertencias en caso de fallo, que se podría verificar mejor
                @mkdir($billetes_dir, 0755, true); 
            }
            
            try {
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                
                // Contenido del Billete
                $pdf->SetFillColor(0, 86, 179); // Azul para el encabezado
                $pdf->SetTextColor(255, 255, 255); // Texto blanco
                $pdf->Cell(190, 10, iconv('UTF-8', 'windows-1252', 'BILLETE DE VUELO ELECTRÓNICO - AEROTEC'), 1, 1, 'C', true);

                $pdf->SetTextColor(0, 0, 0); 
                $pdf->Ln(5);
                
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(60, 8, iconv('UTF-8', 'windows-1252', 'NÚMERO DE RESERVA:'), 0, 0);
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(130, 8, $id_reserva, 0, 1);
                $pdf->Ln(5);
                
                // Detalles del Pasajero
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(190, 8, iconv('UTF-8', 'windows-1252', 'DATOS DEL PASAJERO'), 0, 1, 'L');
                $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
                
                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, 'Nombre Completo:', 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, iconv('UTF-8', 'windows-1252', $nombre . ' ' . $apellido_paterno . ' ' . $apellido_materno), 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, 'Correo:', 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, $correo, 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, iconv('UTF-8', 'windows-1252', 'Teléfono:'), 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, $telefono, 0, 1);
                
                // Detalles del Vuelo y Pago
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(190, 8, 'DETALLES DE VUELO Y PAGO', 0, 1, 'L');
                $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, 'ID de Vuelo:', 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, $id_vuelo, 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, 'Clase:', 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, ucfirst($clase), 0, 1);
                
                $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(45, 7, 'Total Pagado:', 0, 0);
                $pdf->SetFont('Arial', '', 10); $pdf->Cell(145, 7, '$' . number_format($precio_total, 2), 0, 1);

                $pdf_filename = 'billete-' . $id_reserva . '.pdf';
                $pdf_filepath = $billetes_dir . DIRECTORY_SEPARATOR . $pdf_filename;
                
                // Guardar el archivo PDF
                $pdf->Output($pdf_filepath, 'F');
                
            } catch (\Exception $e) {
                // Si falla el PDF, no es crítico para la reserva CSV
                $errores[] = "ADVERTENCIA: La reserva se guardó, pero falló el guardado del PDF (FPDF): " . $e->getMessage();
                $pdf_filename = ''; // Asegurar que no se envíe un nombre de archivo erróneo
            }

            // 3. Redirección de ÉXITO
            // Se usa el ID de reserva como 'auth_token' temporal
            
            // Calculamos el nombre completo del pasajero
            $full_name = trim($nombre . ' ' . $apellido_paterno . ' ' . $apellido_materno);
            
            $redirect_url = 'vuelos_reservados.php?id_reserva=' . urlencode($id_reserva);
            if (!empty($pdf_filename)) {
                $redirect_url .= '&pdf=' . urlencode($pdf_filename);
            }
            // FIX para el error 'pasajero' reportado: incluimos el nombre completo.
            if (!empty($full_name)) {
                $redirect_url .= '&pasajero=' . urlencode($full_name);
            }
            // Aseguramos que el token siempre vaya
            $redirect_url .= '&auth_token=' . urlencode($id_reserva); 

            // Usamos la URL completa que acabamos de construir
            header('Location: ' . $redirect_url);
            exit();

        } else {
            // FALLO. Forzamos la visualización de los errores detallados.
            $errores[] = "FALLO CRÍTICO DE ESCRITURA. Revisar los mensajes de permisos y la estructura de \$reserva_datos en el debug.";
        }
    }
}

// Si llegamos aquí hay errores: mostramos la página con detalles
// ... (El HTML de error se mantiene sin cambios)
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Error al procesar reserva</title>
<link rel="stylesheet" href="styles.css">
<style>
    /* Estilos de error simples para el debug */
    .container {
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
    }
    h2 {
        color: #ff5a6e;
        border-bottom: 2px solid #ff5a6e;
        padding-bottom: 10px;
    }
    .error-detail { 
        background:#ffeef0; 
        border-left:5px solid #ff5a6e; 
        padding:18px; 
        border-radius:8px; 
        color:#600; 
        margin-top: 20px;
    }
    .error-detail h4 {
        margin-top: 0;
        color: #cc0000;
    }
    .debug { 
        background:#f4f4f4; 
        padding:12px; 
        margin-top:12px; 
        border-radius:6px; 
        font-family:monospace; 
        font-size:0.9em; 
        color:#333;
        white-space: pre-wrap;
    }
    .debug strong {
        color: #0056b3;
    }
</style>
</head>
<body>
    <div class="container" style="padding: 40px;">
        <h2>❌ Error al Procesar la Reserva</h2>
        <p>Ha ocurrido un problema al intentar finalizar la reserva. Por favor revise los errores abajo.</p>

        <?php if (!empty($errores)): ?>
            <div class="error-detail">
                <h4>Detalles del Error:</h4>
                <ul>
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h4>Comprobaciones rápidas (útiles para depurar)</h4>
        <div class="debug">
            <strong>Método:</strong> <?= htmlspecialchars($_SERVER['REQUEST_METHOD']) ?><br>
            <strong>POST contiene:</strong> <?= htmlspecialchars(json_encode($_POST, JSON_UNESCAPED_UNICODE)) ?><br>
            <strong>GET contiene:</strong> <?= htmlspecialchars(json_encode($_GET, JSON_UNESCAPED_UNICODE)) ?><br>
            <strong>Archivo reservas:</strong> <?= htmlspecialchars($reservas_file) ?><br>
            <strong>Permisos directorio:</strong> <?= is_writable(dirname($reservas_file)) ? 'writable' : 'NOT writable' ?>
        </div>

        <p style="margin-top:20px;"><a href="vuelos_disponibles.php" style="color:#0056b3;">← Volver a lista de vuelos</a></p>
    </div>
</body>
</html>