<?php
session_start();

// Requiere autenticación (ajustar según necesidad)
// Nota: En un entorno real, 'autenticado' debe ser un sistema robusto
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    // Si la sesión no está autenticada, redirigimos al login
    header("Location: login.php");
    exit;
}

$base_dir = __DIR__ . DIRECTORY_SEPARATOR;
$reservas_file = $base_dir . "reservas.csv";
$vuelos_file 	 = $base_dir . "vuelos.csv";

// Funciones auxiliares
function safe_fgetcsv($handle) {
    // wrapper que usa los 5 parámetros requeridos por PHP moderno
    return fgetcsv($handle, 1000, ',', '"', '\\');
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// 1) Cargar vuelos en array asociativo por id_vuelo (si existe el archivo)
$vuelos = [];
if (file_exists($vuelos_file) && is_readable($vuelos_file)) {
    $handle = @fopen($vuelos_file, 'r');
    if ($handle !== false) {
        // Intentar obtener cabeceras; si no hay, usar claves por defecto
        $headers = safe_fgetcsv($handle);
        if ($headers === false) $headers = ['id_vuelo','aerolinea','origen','destino','fecha_salida','hora_salida','duracion','precio_normal','precio_business','precio_primera'];

        while (($row = safe_fgetcsv($handle)) !== false) {
            if (empty($row)) continue;
            // proteger contra filas más cortas
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $vuelo = array_combine($headers, $row);
            $key = trim($vuelo['id_vuelo'] ?? '');
            if ($key !== '') $vuelos[$key] = $vuelo;
        }
        fclose($handle);
    }
}

// 2) Cargar reservas (si existe), y filtrar por usuario si aplica
$reservas = [];
if (file_exists($reservas_file) && is_readable($reservas_file)) {
    $handle = @fopen($reservas_file, 'r');
    if ($handle !== false) {
        $headers = safe_fgetcsv($handle);
        // Ajustar los headers si son insuficientes
        if ($headers === false || count($headers) < 10) $headers = ['id_reserva','id_vuelo','nombre','apellido_paterno','apellido_materno','correo','telefono','clase','precio_total','fecha_reserva','pdf_filename'];

        // Determinar filtro por usuario (si existe correo en sesión)
        $user_correo = $_SESSION['correo'] ?? null;

        while (($row = safe_fgetcsv($handle)) !== false) {
            if (empty($row)) continue;
            // Asegurar que la fila tiene suficientes columnas para la combinación
            if (count($row) < count($headers)) $row = array_pad($row, count( $headers), '');
            $r = array_combine($headers, $row);

            // Si hay correo de sesión, mostrar solo las reservas del usuario
            if ($user_correo) {
                if (trim($r['correo'] ?? '') === trim($user_correo)) {
                    $reservas[] = $r;
                }
            } else {
                // si no hay correo en sesión, agregar todas (útil para debugging/admin)
                $reservas[] = $r;
            }
        }
        fclose($handle);
    }
}

// === CRÍTICO: CAMBIAR EL ORDEN PARA SIMULAR UNA PILA (LIFO) ===
// Invertimos el array para que el último elemento (la reserva más reciente, 
// que se agregó al final del archivo CSV y, por tanto, al final del array) 
// sea el primero en mostrarse.
$reservas = array_reverse($reservas); 
// =============================================================

// --- LÓGICA DE MENSAJE DE CONFIRMACIÓN (FIX para el error 'pasajero') ---
$success_message = '';
$reserved_id = h($_GET['id_reserva'] ?? '');
$pdf_file = h($_GET['pdf'] ?? '');

// 1. Obtener el nombre del pasajero de forma robusta
// Se revisa primero $_GET (redundancia de procesar_reserva) y luego $_SESSION (fix principal)
$passenger_name = h($_GET['pasajero'] ?? ($_SESSION['pasajero'] ?? ''));

if (!empty($reserved_id) && !empty($passenger_name)) {
    // 2. Generar el mensaje
    $success_message = "¡Reserva Exitosa! Gracias, " . $passenger_name . ".";
    
    if (!empty($pdf_file)) {
        $success_message .= " Tu billete (" . $reserved_id . ") ha sido generado y está listo para descargar.";
    } else {
        $success_message .= " Tu reserva (" . $reserved_id . ") ha sido confirmada.";
    }
    
    // 3. Limpiar la sesión para que el mensaje no aparezca en futuras cargas de la página
    unset($_SESSION['pasajero']); 
}
// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Mis Reservas - AEROTEC</title>
<link rel="stylesheet" href="styles.css">
<style>

/* --- TABLA MODERNA --- */
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 25px;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Encabezados */
.table thead th {
    background: linear-gradient(135deg, #6a0dad, #9a3ffd);
    color: #fff;
    text-align: left;
    padding: 14px;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Celdas */
.table td {
    padding: 14px;
    font-size: 0.95em;
    color: #333;
    border-bottom: 1px solid #f2f2f2;
}

/* Hover */
.table tbody tr:hover {
    background: #f8f3ff;
    transition: 0.2s ease-in-out;
}

/* Última fila sin borde */
.table tbody tr:last-child td {
    border-bottom: none;
}

/* --- BOTONES --- */
.action-btn {
    display: inline-block;
    padding: 10px 14px;
    font-size: 0.88em;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    color: #fff;
    background: #7b20bf;
    margin-right: 6px;
    transition: 0.25s ease-in-out;
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}

.action-btn:hover {
    background: #5e178f;
    transform: translateY(-2px);
}

/* botón Eliminar */
.delete-btn {
    background: #e0002a;
}

.delete-btn:hover {
    background: #b30020;
}

/* MENSAJE VACÍO */
.empty {
    text-align: center;
    padding: 40px;
    margin-top: 40px;
    background: #ffffff;
    border-radius: 12px;
    border: 2px dashed #9a3ffd;
    color: #666;
    font-size: 1.1em;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.empty a {
    display: inline-block;
    margin-top: 15px;
    padding: 12px 22px;
    background: #7b20bf;
    color: #fff;
    text-decoration: none;
    border-radius: 30px;
    font-weight: bold;
}

.empty a:hover {
    background: #5e178f;
}

/* ESTILO PARA MENSAJE DE ÉXITO */
.success-box {
    padding: 20px;
    margin-top: 20px;
    background: #e6ffec; /* Verde muy claro */
    color: #1a7a2e; /* Verde oscuro */
    border: 1px solid #94d7a8;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1.1em;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

</style>

</head>
<body>
<header class="main-header">
    <div class="container">
        <div class="logo">
            <img src="images/LOGO.jpg" alt="Logo AEROTEC">
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.html" >Inicio</a></li>
                <li><a href="vuelos_disponibles.php">Vuelos Disponibles</a></li>
                <li><a href="vuelos_tiempo_real.php">Vuelos en Tiempo Real</a></li>
                <li><a href="vuelos_reservados.php" class="active">Vuelos Reservados</a></li>
                <li><a href="logout.php" class="logout-btn">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </div>
</header>

    <main class="container">
        <h1>Mis Reservas de Vuelo</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-box">
                <?php echo h($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($reservas)): ?>
            <div class="empty">
                <p>No hay reservas registradas para este usuario.</p>
                <p><a href="vuelos_disponibles.php">← Volver al listado de vuelos</a></p>
            </div>
        <?php else: ?>
            <table class="table" aria-describedby="listado-reservas">
                <thead>
                    <tr>
                        <th>ID Reserva</th>
                        <th>Vuelo</th>
                        <th>Ruta</th>
                        <th>Fecha</th>
                        <th>Clase</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reservas as $r): 
                    $id_res = $r['id_reserva'] ?? '';
                    $id_vuelo = $r['id_vuelo'] ?? '';
                    $vuelo = $vuelos[$id_vuelo] ?? null;
                ?>
                    <tr>
                        <td><?php echo h($id_res); ?></td>
                        <td><?php echo h($vuelo['id_vuelo'] ?? $id_vuelo) . ' ' . (isset($vuelo['aerolinea']) ? '(' . h($vuelo['aerolinea']) . ')' : ''); ?></td>
                        <td><?php echo h($vuelo['origen'] ?? 'N/A') . ' → ' . h($vuelo['destino'] ?? 'N/A'); ?></td>
                        <td><?php echo h(($vuelo['fecha_salida'] ?? $r['fecha_reserva']) . ' ' . ($vuelo['hora_salida'] ?? '')); ?></td>
                        <td><?php echo h($r['clase'] ?? 'N/A'); ?></td>
                        <td>
                            <a class="action-btn" href="download_boleto.php?id_reserva=<?php echo urlencode($id_res); ?>">📄 Descargar</a>
                            <!-- FIX: Se elimina el confirm() no soportado en este entorno -->
                            <a class="action-btn delete-btn" href="eliminar_reserva.php?id_reserva=<?php echo urlencode($id_res); ?>">🗑 Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    </body>
</html>