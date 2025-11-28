<?php
// Define que no se debe enviar ninguna cabecera antes del código PHP
header('Content-Type: text/html; charset=utf-8'); 

// ==============================================================================
// 1. SIMULACIÓN DE DATOS DE VUELOS EN TIEMPO REAL CON VARIACIONES AUTOMÁTICAS
// ==============================================================================

function simularCambiosVuelo($vuelo) {
    // $vuelo = [ID, Código, Aerolínea, Origen, Destino, Fecha, Salida, Llegada, Estado, Puerta]

    $estado = $vuelo[8];

    // 1. Cambios de estado
    if ($estado === "En Vuelo") {

        $rand = rand(1, 100);

        if ($rand <= 10) {
            // 10% de posibilidades de aterrizar
            $vuelo[8] = "Aterrizado";
        } elseif ($rand <= 25) {
            // 15% de retrasarse
            $vuelo[8] = "Retrasado";
        }

    } elseif ($estado === "Retrasado") {

        $rand = rand(1, 100);

        if ($rand <= 30) {
            // 30% vuelve a estar en vuelo
            $vuelo[8] = "En Vuelo";
        }

        // 20% de posibilidades de retrasarse más
        if (rand(1, 100) <= 20) {
            $vuelo[7] = date("H:i", strtotime($vuelo[7] . " +5 minutes"));
        }

    }

    // 2. Si no ha aterrizado ni cancelado, mover horario
    if ($vuelo[8] !== "Aterrizado" && $vuelo[8] !== "Cancelado") {

        $cambioMin = rand(-5, 10); // puede adelantarse o retrasarse
        $vuelo[7] = date("H:i", strtotime($vuelo[7] . " $cambioMin minutes"));

        // nunca permitir horas negativas
        if (strtotime($vuelo[7]) < strtotime("00:00")) {
            $vuelo[7] = "00:00";
        }
    }

    // 3. Cambiar puerta si sigue activo
    if ($vuelo[8] === "En Vuelo" || $vuelo[8] === "Retrasado") {
        if (rand(1, 100) <= 10) { // 10%
            $vuelo[9] = rand(1, 12);
        }
    }

    return $vuelo;
}


function obtenerVuelosTiempoReal() {

    // Los datos base deben estar fuera de la función de simulación
    $vuelos_base = [
        // ID, Código, Aerolínea, Origen, Destino, Fecha, Salida, Llegada, Estado, Puerta
        [2001, 'IB-6701', 'Iberia',     'Madrid',    'CDMX', date('Y-m-d'), '03:10', '10:30', 'Aterrizado', '2'],
        [2002, 'AM-005',  'Air Mexico', 'Nueva York', 'CDMX', date('Y-m-d'), '05:40', '11:45', 'En Vuelo', '4'],
        [2003, 'AA-211',  'American Air', 'Dallas',    'CDMX', date('Y-m-d'), '07:20', '12:00', 'Retrasado', '1A'],
        [2004, 'DL-808',  'Delta',      'Atlanta',   'CDMX', date('Y-m-d'), '09:00', '14:20', 'En Vuelo', '9'],
        [2005, 'AR-301',  'AEROTEC',    'Cancún',    'CDMX', date('Y-m-d'), '11:00', '15:15', 'Cancelado', '-'],
        [2006, 'LH-498',  'Lufthansa',  'Frankfurt', 'CDMX', date('Y-m-d'), '12:30', '18:50', 'En Vuelo', '6'],
    ];

    // Aplicar simulación a cada vuelo
    $vuelos_actualizados = [];
    foreach ($vuelos_base as $v) {
        $vuelos_actualizados[] = simularCambiosVuelo($v);
    }

    // ====================================================================
    // 🚩 LÓGICA DE ORDENACIÓN PRIORIZADA POR ESTADO
    // ====================================================================

    usort($vuelos_actualizados, function($a, $b) {
        // Mapeo de estados a prioridad numérica (menor número = mayor prioridad)
        $priorityMap = [
            'Aterrizado' => 1,
            'En Vuelo'   => 2,
            'Retrasado'  => 3,
            'Cancelado'  => 4,
        ];

        $estadoA = $a[8];
        $estadoB = $b[8];
        $prioA = $priorityMap[$estadoA] ?? 99; // 99 si el estado es desconocido
        $prioB = $priorityMap[$estadoB] ?? 99;

        // 1. Comparar por Prioridad de Estado (Aterrizado, En Vuelo, Retrasado, Cancelado)
        if ($prioA !== $prioB) {
            return $prioA <=> $prioB; // El de menor número (Aterrizado) va primero
        }

        // 2. Si las prioridades son iguales, ordenar por Hora de Llegada
        return strtotime($a[7]) - strtotime($b[7]);
    });

    return $vuelos_actualizados;
}


// ==============================================================================
// 2. LÓGICA DE CARGA DE RESERVAS Y PETICIONES AJAX
// ==============================================================================

// Si es una petición AJAX → responde solo JSON
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(obtenerVuelosTiempoReal());
    exit;
}

$vuelos_tiempo_real = obtenerVuelosTiempoReal(); // Cargar datos para la vista HTML

// Carga de reservas y pasajeros
$base_dir = __DIR__ . DIRECTORY_SEPARATOR;
$reservas_file = $base_dir . "reservas.csv";
$pasajeros_por_vuelo = []; 

function safe_fgetcsv($handle) {
    // Se ha eliminado el espacio no rompible del argumento del delimitador.
    return fgetcsv($handle, 1000, ',', '"', '\\');
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if (file_exists($reservas_file) && is_readable($reservas_file)) {
    $handle = @fopen($reservas_file, 'r');
    if ($handle !== false) {
        $headers = safe_fgetcsv($handle);
        $expected_headers = ['id_reserva','id_vuelo','nombre','apellido_paterno','apellido_materno','correo','telefono','clase','precio_total','fecha_reserva','pdf_filename'];
        
        if ($headers === false || count($headers) < count($expected_headers)) {
            $headers = $expected_headers;
        }

        while (($row = safe_fgetcsv($handle)) !== false) {
            if (empty($row)) continue;
            if (count($row) < count($headers)) $row = array_pad($row, count(array_keys($headers)), '');
            $r = array_combine($headers, $row);

            $codigo_vuelo_reserva = trim($r['id_vuelo'] ?? ''); 

            $nombre_completo = trim(h($r['nombre'] . ' ' . $r['apellido_paterno']));
            
            if (!isset($pasajeros_por_vuelo[$codigo_vuelo_reserva])) {
                $pasajeros_por_vuelo[$codigo_vuelo_reserva] = [];
            }
            $pasajeros_por_vuelo[$codigo_vuelo_reserva][] = $nombre_completo;
        }
        fclose($handle);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuelos en Tiempo Real - AEROTEC</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* Contenedor principal: simplificado a un flujo vertical para ocupar todo el ancho */
        .real-time-content {
            /* Flexbox ya no es necesario aquí, pero se mantiene la estructura */
            gap: 20px;
        }

        /* Tabla de Vuelos Principal */
        .flight-table-container {
            width: 100%; /* Ocupa el 100% del contenedor padre */
        }
        .real-time-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .real-time-table th, .real-time-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .real-time-table th {
            background-color: #6a0dad; 
            color: white;
            font-size: 0.85em;
            text-transform: uppercase;
        }
        .real-time-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        /* Estilos de los badges de estado */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.85em;
            color: white;
            text-align: center;
        }
        .status-aterrizado { background-color: #4caf50; }
        .status-en-vuelo { background-color: #2196f3; }
        .status-retrasado { background-color: #ff9800; color: #333; }
        .status-cancelado { background-color: #f44336; }
        
        /* Animación para el cambio En Vuelo -> Aterrizado */
        .landing-anim {
            background-color: #a5d6a7 !important; /* Verde claro */
            transition: background-color 0s;
            animation: flashGreen 1.5s ease-out;
        }
        @keyframes flashGreen {
            0% { background-color: #ffff8d; box-shadow: 0 0 10px #ffff8d; }
            50% { background-color: #4caf50; }
            100% { background-color: #a5d6a7; }
        }

        /* Estilo para las notificaciones en pantalla */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #6a0dad;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.5s, transform 0.5s;
            transform: translateY(100%);
            max-width: 300px;
        }
        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        /* Estilos de pasajeros */
        .pasajeros-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 80px; 
            overflow-y: auto; 
            font-size: 0.8em;
        }
        .pasajeros-list li {
            padding: 1px 0;
            border-bottom: 1px dotted #eee;
            white-space: nowrap; 
        }
        .pasajeros-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<audio id="soundAlert" src="https://assets.mixkit.co/sfx/preview/mixkit-software-interface-essential-2578.mp3" preload="auto"></audio>

<header class="main-header">
    <div class="container">
        <div class="logo">
            <!-- La ruta de la imagen ha sido ajustada, asumiendo que el archivo de imagen 'LOGO.JPG' está en una carpeta 'images' -->
            <img src="images/LOGO.jpg" alt="Logo AEROTEC">
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.html">Inicio</a></li>
                <li><a href="vuelos_disponibles.php">Vuelos Disponibles</a></li>
                <li><a href="vuelos_tiempo_real.php" class="active">Vuelos en Tiempo Real</a></li>
                <li><a href="vuelos_reservados.php">Vuelos Reservados</a></li>
                
            </ul>
        </nav>
    </div>
</header>

<main class="flight-page">
    <div class="container">
        <h2><i class="fas fa-globe" style="color: var(--secondary-color);"></i> Monitoreo de Vuelos en Tiempo Real</h2>
        <p>Actualización automática cada 5 segundos.</p>

        <div class="real-time-content">
            <!-- Se ha eliminado el panel de estadísticas lateral (#statsPanel) -->

            <div class="flight-table-container">
                <table class="real-time-table" id="tablaVuelos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Aerolínea</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Fecha</th>
                            <th>Salida Est.</th>
                            <th>Llegada Est.</th>
                            <th>Estado</th>
                            <th>Puerta</th>
                            <th>Pasajeros</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <!-- El contenido se llenará con JavaScript -->
                    </tbody>
                </table>
                
                <p id="ultimaAct" style="margin-top: 30px; text-align: right; color: var(--light-text-color);">
                    Última actualización: —
                </p>
            </div>
        </div>
    </div>
</main>

<div class="notification" id="statusNotification"></div>

<script>
// ===============================================
// 🔄 FUNCIONES GLOBALES Y ESTADO
// ===============================================

// Almacena el estado previo de los vuelos para detectar cambios
let previousVuelosState = {};

// Obtiene el número de pasajeros desde el entorno PHP (solo para simulación)
const pasajerosPorVuelo = <?php echo json_encode($pasajeros_por_vuelo); ?>;

// ===============================================
// 🔄 FUNCIÓN PARA ACTUALIZAR TABLA CADA 5 SEGUNDOS
// ===============================================

function cargarVuelos() {
    // La petición AJAX ahora usa el mismo archivo (vuelos_tiempo_real.php) con el parámetro ajax=1
    fetch("vuelos_tiempo_real.php?ajax=1")
        .then(r => r.json())
        .then(data => {
            const tbody = document.querySelector("#tablaVuelos tbody");
            tbody.innerHTML = "";
            let newVuelosState = {};
            
            data.forEach(v => {
                // v[1]: Código, v[8]: Estado, v[9]: Puerta

                const codigoVuelo = v[1];
                const estado = v[8];
                let estadoClass = "";
                let rowClass = "";
                
                // 1. Detección de Cambios y Animación/Sonido
                const previousEstado = previousVuelosState[codigoVuelo];

                if (estado === "Aterrizado") {
                    estadoClass = "status-aterrizado";
                    if (previousEstado === "En Vuelo") {
                        rowClass = "landing-anim"; // Aplica animación si el estado cambió a Aterrizado
                        playSoundAlert();
                        showNotification(`¡Aterrizaje! Vuelo ${codigoVuelo} ha aterrizado.`, 'success');
                    }
                } else if (estado === "En Vuelo") {
                    estadoClass = "status-en-vuelo";
                } else if (estado === "Retrasado") {
                    estadoClass = "status-retrasado";
                    if (previousEstado !== "Retrasado" && previousEstado !== undefined) {
                        playSoundAlert();
                        showNotification(`¡Retraso! Vuelo ${codigoVuelo} ha sido retrasado.`, 'warning');
                    }
                } else if (estado === "Cancelado") {
                    estadoClass = "status-cancelado";
                    if (previousEstado !== "Cancelado" && previousEstado !== undefined) {
                        playSoundAlert();
                        showNotification(`¡Cancelado! Vuelo ${codigoVuelo} ha sido cancelado.`, 'error');
                    }
                }
                
                // 2. Almacenar nuevo estado
                newVuelosState[codigoVuelo] = estado; // Almacenar estado actual

                // 3. Renderizado de Pasajeros
                const pasajeros = pasajerosPorVuelo[codigoVuelo] || [];
                let pasajerosHTML = '';
                if (pasajeros.length > 0) {
                    pasajerosHTML = `<ul class="pasajeros-list">`;
                    // Limitar la lista a 3 o 4 para no ocupar demasiado espacio
                    const displayPasajeros = pasajeros.slice(0, 4); 
                    displayPasajeros.forEach(p => {
                        pasajerosHTML += `<li>${p}</li>`;
                    });
                    
                    if (pasajeros.length > 4) {
                        pasajerosHTML += `<li>... y ${pasajeros.length - 4} más</li>`;
                    }
                    pasajerosHTML += `</ul><span style="font-size: 0.75em; color: #6a0dad;">Total: ${pasajeros.length}</span>`;
                } else {
                    pasajerosHTML = `<span style="color:#999;">Sin reservas</span>`;
                }

                // 4. Inserción de Fila
                tbody.innerHTML += `
                <tr class="${rowClass}">
                    <td>${v[1]}</td>
                    <td>${v[2]}</td>
                    <td>${v[3]}</td>
                    <td>${v[4]}</td>
                    <td>${v[5]}</td>
                    <td>${v[6]}</td>
                    <td>${v[7]}</td>
                    <td><span class="status-badge ${estadoClass}">${estado}</span></td>
                    <td>${v[9]}</td>
                    <td>${pasajerosHTML}</td> 
                </tr>`;
            });

            // 5. Actualizar Hora y Estado
            document.getElementById("ultimaAct").innerHTML =
                "Última actualización: <span style='font-weight: bold;'>" + new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + "</span>";

            // 6. Almacenar el nuevo estado para la próxima comparación
            previousVuelosState = newVuelosState;
        })
        .catch(error => {
            console.error('Error al cargar vuelos:', error);
            document.getElementById("ultimaAct").innerHTML = "Última actualización: <span style='color: red;'>Error de conexión</span>";
        });
}

// ===============================================
// 🎧 FUNCIONES DE SONIDO Y NOTIFICACIÓN
// ===============================================

function playSoundAlert() {
    const audio = document.getElementById('soundAlert');
    // Esto es un workaround para permitir la reproducción de audio en navegadores modernos.
    if (audio) {
        audio.currentTime = 0; // Reinicia para poder reproducir inmediatamente
        // La promesa catch es para evitar errores si la reproducción es bloqueada (ej. sin interacción del usuario)
        audio.play().catch(e => console.log("Audio play blocked (requires user interaction):", e));
    }
}

function showNotification(message, type = 'info') {
    const notification = document.getElementById('statusNotification');
    notification.textContent = message;
    
    // Cambiar el color de fondo según el tipo
    notification.style.backgroundColor = (type === 'success') ? '#4caf50' : 
                                         (type === 'warning') ? '#ff9800' : 
                                         (type === 'error') ? '#f44336' : 
                                         '#6a0dad';

    notification.classList.add('show');
    
    // Ocultar después de 4 segundos
    setTimeout(() => {
        notification.classList.remove('show');
    }, 4000);
}


// ===============================================
// 🚀 INICIALIZACIÓN
// ===============================================

// Cargar al inicio y preparar el estado inicial
cargarVuelos();

// Cargar automáticamente cada 5 segundos
setInterval(cargarVuelos, 5000);
</script>

</body>
</html>