<?php
// eliminar_reserva.php
// Script para eliminar una reserva de vuelos y su boleto PDF asociado.

session_start();

// 1. Requiere autenticación
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Obtener y validar el ID de reserva
$id_reserva = $_GET['id_reserva'] ?? null;

if (!$id_reserva) {
    die("Error: ID de reserva faltante.");
}

$base_dir = __DIR__ . DIRECTORY_SEPARATOR;
$reservas_file = $base_dir . "reservas.csv";
$tmp_file = $base_dir . "reservas_tmp.csv";
$billetes_dir = $base_dir . "billetes" . DIRECTORY_SEPARATOR;
$reserva_eliminada = false;
$pdf_borrado = false;

// Función auxiliar
function safe_fgetcsv($handle) {
    return fgetcsv($handle, 1000, ',', '"', '\\');
}
function safe_fputcsv($handle, $fields) {
    return fputcsv($handle, $fields, ',', '"', '\\');
}

// Abrir archivos para lectura y escritura
$fp_r = @fopen($reservas_file, "r");
$fp_w = @fopen($tmp_file, "w");

if ($fp_r === false || $fp_w === false) {
    if ($fp_r) fclose($fp_r);
    if ($fp_w) fclose($fp_w);
    die("Error al abrir los archivos de reservas. Verifique permisos.");
}

// Procesar el archivo CSV
$keys = safe_fgetcsv($fp_r); // Leer cabeceras
if ($keys) {
    safe_fputcsv($fp_w, $keys); // Escribir cabeceras en el temporal
}

$linea_actual = 0;
while (($data = safe_fgetcsv($fp_r)) !== false) {
    $linea_actual++;
    if (empty($data)) continue;
    
    // Si la fila actual tiene menos columnas que las cabeceras, rellenar
    if ($keys && count($data) < count($keys)) {
        $data = array_pad($data, count($keys), '');
    }

    $r = $keys ? array_combine($keys, $data) : $data;
    
    // Comparar IDs (usando trim() para limpieza)
    if (trim($r['id_reserva'] ?? '') === trim($id_reserva)) {
        // Encontramos la reserva a eliminar
        $reserva_eliminada = true;

        // 3. Intentar borrar el PDF asociado
        $pdf_filename = $r['pdf_filename'] ?? '';
        if (!empty($pdf_filename)) {
            $pdf_path = $billetes_dir . $pdf_filename;
            if (file_exists($pdf_path) && @unlink($pdf_path)) {
                $pdf_borrado = true;
            }
        }
    } else {
        // Escribir la reserva al archivo temporal (se mantiene)
        safe_fputcsv($fp_w, $data);
    }
}

// Cerrar ambos archivos
fclose($fp_r);
fclose($fp_w);

if ($reserva_eliminada) {
    // 4. Reemplazar el archivo original con el temporal
    if (rename($tmp_file, $reservas_file)) {
        // 5. Redirigir al listado de reservas con un mensaje de éxito
        header("Location: vuelos_reservados.php?status=deleted");
        exit;
    } else {
        // Si falla rename, intentamos un fallback o mostramos un error
        unlink($tmp_file); // Borrar temporal si no se puede renombrar
        die("Error crítico: No se pudo actualizar el archivo de reservas. Revise permisos.");
    }
} else {
    // Si no se encontró la reserva, borramos el temporal
    unlink($tmp_file);
    // Redirigir y mostrar mensaje de error suave
    header("Location: vuelos_reservados.php?status=notfound");
    exit;
}
?>