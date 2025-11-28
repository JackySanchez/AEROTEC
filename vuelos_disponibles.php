<?php
// CRÍTICO: Iniciar sesión y proteger la página
session_start();

// El archivo CSV ahora solo se usa para datos estáticos si la simulación no tiene todo
$csv_file = 'vuelos.csv';

// ==============================================================================
// 1. LÓGICA DE SIMULACIÓN DE VUELOS EN TIEMPO REAL 
//    (Simula cambios en estado y hora de llegada)
// ==============================================================================

function simularCambiosVuelo($vuelo) {
    // $vuelo = [ID, Código, Aerolínea, Origen, Destino, Fecha, Salida, Llegada, Estado, Puerta]

    $estado = $vuelo[8];

    // Simulación de transición de estados
    if ($estado === "En Vuelo") {
        $rand = rand(1, 100);
        if ($rand <= 10) {
            $vuelo[8] = "Aterrizado";
        } elseif ($rand <= 25) {
            $vuelo[8] = "Retrasado";
        }
    } elseif ($estado === "Retrasado") {
        $rand = rand(1, 100);
        if ($rand <= 30) {
            $vuelo[8] = "En Vuelo";
        }
        // Retraso adicional si ya estaba retrasado
        if (rand(1, 100) <= 20) {
            $vuelo[7] = date("H:i", strtotime($vuelo[7] . " +5 minutes"));
        }
    }

    // Simulación de fluctuación de hora de llegada ($v[7])
    if ($vuelo[8] !== "Aterrizado" && $vuelo[8] !== "Cancelado") {
        $cambioMin = rand(-5, 10);
        $vuelo[7] = date("H:i", strtotime($vuelo[7] . " $cambioMin minutes"));

        if (strtotime($vuelo[7]) < strtotime("00:00")) {
            $vuelo[7] = "00:00";
        }
    }

    // Simulación de cambio de Puerta ($v[9])
    if ($vuelo[8] === "En Vuelo" || $vuelo[8] === "Retrasado") {
        if (rand(1, 100) <= 10) {
            $vuelo[9] = rand(1, 12);
        }
    }

    return $vuelo;
}

function obtenerVuelosTiempoReal() {
    // Definimos la base de datos de vuelos base (IDs deben coincidir con vuelos.csv)
    // Orden de Columnas: ID, Código, Aerolínea, Origen, Destino, Fecha, Salida, Llegada, Estado, Puerta
    $vuelos_base = [
        [2001, 'IB-6701', 'Iberia',     'Madrid',    'CDMX', date('Y-m-d'), '03:10', '10:30', 'En Vuelo', '2'],
        [2002, 'AM-005',  'Air Mexico', 'Nueva York', 'CDMX', date('Y-m-d'), '05:40', '11:45', 'En Vuelo', '4'],
        [2003, 'AA-211',  'American Air', 'Dallas',    'CDMX', date('Y-m-d'), '07:20', '12:00', 'En Vuelo', '1A'],
        [2004, 'DL-808',  'Delta',      'Atlanta',   'CDMX', date('Y-m-d'), '09:00', '14:20', 'En Vuelo', '9'],
        [2005, 'AR-301',  'AEROTEC',    'Cancún',    'CDMX', date('Y-m-d'), '11:00', '15:15', 'En Vuelo', '-'],
        [2006, 'LH-498',  'Lufthansa',  'Frankfurt', 'CDMX', date('Y-m-d'), '12:30', '18:50', 'En Vuelo', '6'],
        [2007, 'AE-101',  'AEROTEC',    'Houston', 'CDMX', date('Y-m-d'), '07:00', '11:00', 'En Vuelo', '10'], 
    ];

    $vuelos_actualizados = [];
    foreach ($vuelos_base as $v) {
        $vuelos_actualizados[] = simularCambiosVuelo($v);
    }
    
    return $vuelos_actualizados; 
}


// ==============================================================================
// 2. LÓGICA DE PROCESAMIENTO: LECTURA, FILTRADO Y ACTUALIZACIÓN DINÁMICA
// ==============================================================================

// 2a. Obtener los datos dinámicos de los vuelos simulados
$vuelos_tiempo_real_simulados = obtenerVuelosTiempoReal();
$datos_dinamicos_tiempo_real = []; // Almacena todos los datos dinámicos
foreach ($vuelos_tiempo_real_simulados as $v) {
    // $v[1] es el Código del Vuelo (id_vuelo)
    $vuelo_id = trim($v[1]);
    $datos_dinamicos_tiempo_real[$vuelo_id] = [
        'hora_llegada_real' => $v[7], 
        'estado_actual' => $v[8]
    ];
}


// 2b. Leer el CSV, aplicar el filtro de Cancelado y actualizar la hora
$vuelos_disponibles = []; // Esta será la lista final
if (($handle = fopen($csv_file, 'r')) !== FALSE) {
    $header = fgetcsv($handle, 1000, ',', '"', '\\');
    
    $keys = [
        'id_vuelo', 'aerolinea', 'origen', 'destino', 'fecha_salida', 
        'hora_salida', 'duracion', 'precio_normal', 'precio_business', 
        'precio_primera'
    ];

    while (($data = fgetcsv($handle, 1000, ',', '"', '\\')) !== FALSE) {
        if (!empty($data) && count($data) >= count($keys)) {
            $vuelo = array_combine($keys, $data);
            $vuelo_id = trim($vuelo['id_vuelo']);
            
            // Buscar datos dinámicos
            $datos_dinamicos = $datos_dinamicos_tiempo_real[$vuelo_id] ?? null;

            $estado_actual = $datos_dinamicos['estado_actual'] ?? 'Programado';

            // === CRÍTICO: FILTRADO POR CANCELADO ===
            if ($estado_actual === 'Cancelado') {
                continue; // Si está cancelado, pasa al siguiente vuelo (no se añade a disponibles)
            }
            
            // Limpieza y conversión de precios
            $vuelo['precio_normal'] = floatval(str_replace('$', '', trim($vuelo['precio_normal'] ?? '0')));
            $vuelo['precio_business'] = floatval(str_replace('$', '', trim($vuelo['precio_business'] ?? '0')));
            $vuelo['precio_primera'] = floatval(str_replace('$', '', trim($vuelo['precio_primera'] ?? '0')));
            
            // === CRÍTICO: SOBREESCRIBIR HORA DE LLEGADA CON EL VALOR DINÁMICO ===
            // (La columna 'hora_salida' se usa como Hora de Llegada en la tabla HTML)
            if ($datos_dinamicos) {
                $vuelo['hora_salida'] = $datos_dinamicos['hora_llegada_real'];
            }
            
            $vuelo['estado_actual'] = $estado_actual;
            $vuelos_disponibles[] = $vuelo;
        }
    }
    fclose($handle);
}


// ==============================================================================
// 3. ALGORITMO DE ORDENAMIENTO (Bubble Sort - Por Hora de Llegada Dinámica)
// ==============================================================================
function ordenar_vuelos_bubble_sort(array &$vuelos) {
    $n = count($vuelos);
    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = 0; $j < $n - 1 - $i; $j++) {
            // Ordena por la hora de llegada (ahora dinámica en 'hora_salida')
            if (($vuelos[$j]['hora_salida'] ?? '') > ($vuelos[$j + 1]['hora_salida'] ?? '')) {
                $temp = $vuelos[$j];
                $vuelos[$j] = $vuelos[$j + 1];
                $vuelos[$j + 1] = $temp;
            }
        }
    }
}
ordenar_vuelos_bubble_sort($vuelos_disponibles);


// ==============================================================================
// 4. PREPARACIÓN DE DATOS PARA EL MENÚ Y FORMATOS
// ==============================================================================

function format_price($price) {
    if (!is_numeric($price)) return '$0.00';
    return '$' . number_format($price, 2);
}

// Enlace de cerrar sesión para el menú
$auth_link = '<li><a href="logout.php" class="btn-logout">Cerrar Sesión</a></li>';

// Datos destacados para el Summary Card (Usamos el primer vuelo ordenado)
$featured_vuelo = $vuelos_disponibles[0] ?? [
    'aerolinea' => 'AEROTEC', 
    'origen' => 'N/A', 
    'destino' => 'N/A', 
    'hora_salida' => 'N/A', 
    'duracion' => 'N/A'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEROTEC - Vuelos Disponibles</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/LOGO.JPG" type="image/jpeg">
    <style>
        /* Estilos específicos para la página de Vuelos */
        .summary-card {
            display: flex;
            justify-content: space-between;
            padding: 25px 40px;
            background: var(--primary-color); 
            color: white;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .summary-item {
            display: flex;
            flex-direction: column;
            text-align: center;
        }
        .summary-item .label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        .summary-item .value {
            font-size: 2em;
            font-weight: 700;
        }
        .summary-item .featured-airline,
        .summary-item .arrival-time {
            color: var(--accent-color); 
        }
        .summary-item .route-value {
             font-size: 2.5em; 
        }

        /* Estilos de tabla dinámicos */
        .vuelos-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg-color);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .vuelos-table thead {
            background-color: var(--secondary-color);
            color: white;
            text-transform: uppercase;
            font-size: 0.85em; 
        }
        .vuelos-table th, .vuelos-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .vuelos-table td:last-child {
            text-align: center;
        }
        .btn-select {
            background-color: var(--secondary-color);
            color: white; 
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .btn-select:hover {
            background-color: var(--hover-color);
        }
        
        /* Estilos del botón para vuelos cancelados/no disponibles */
        .btn-disabled {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
            pointer-events: none; /* Deshabilita el clic */
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        /* Estilos del menú dinámico */
        .btn-logout {
            background-color: var(--accent-color, #b366ff);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn-logout:hover {
             background-color: var(--secondary-color, #8a2be2);
        }

        /* Badge de estado del vuelo (opcional) */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
            margin-left: 5px;
        }
        .status-aterrizado { background-color: #4caf50; }
        .status-en-vuelo { background-color: #2196f3; }
        .status-retrasado { background-color: #ff9800; color: #333; }
        
    </style>
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <img src="images/LOGO.JPG" alt="Logo AEROTEC">
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" >Inicio</a></li>
                    <li><a href="vuelos_disponibles.php" class="active">Vuelos Disponibles</a></li>
                    <li><a href="vuelos_tiempo_real.php">Vuelos en Tiempo Real</a></li>
                    <li><a href="vuelos_reservados.php">Vuelos Reservados</a></li>
                    
                    <?php echo $auth_link; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="flight-page">
        <div class="container">
            
            <h2 style="margin-top: 0;">✈️ Vuelos de Llegada Disponibles</h2>
            <p>Lista de vuelos activos, ordenada por **Hora de Llegada Estimada**. Los vuelos Cancelados no se muestran, y si han **Aterrizado** no pueden ser comprados.</p>
            
            <div class="summary-card">
                <div class="summary-item">
                    <span class="label">Aerolínea Destacada</span>
                    <span class="value featured-airline"><?php echo htmlspecialchars($featured_vuelo['aerolinea']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Ruta Próxima</span>
                    <span class="value route-value"><?php echo htmlspecialchars($featured_vuelo['origen']); ?> &rarr; <?php echo htmlspecialchars($featured_vuelo['destino']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Hora de Llegada</span>
                    <span class="value arrival-time"><?php echo htmlspecialchars($featured_vuelo['hora_salida']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Duración del Vuelo</span>
                    <span class="value"><?php echo htmlspecialchars($featured_vuelo['duracion']); ?></span>
                </div>
            </div>

            <table class="vuelos-table">
                <thead>
                    <tr>
                        <th>ID Vuelo</th>
                        <th>Aerolínea</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Hora de Llegada</th>
                        <th>Duración</th>
                        <th>Precio Normal</th>
                        <th>Precio Business</th>
                        <th>Precio Primera</th>
                        <th>Estado</th> 
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vuelos_disponibles)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center;">No hay vuelos disponibles en este momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vuelos_disponibles as $vuelo): 
                            $estado = $vuelo['estado_actual'] ?? 'Programado';
                            // Solo se puede comprar si NO ha aterrizado
                            $can_buy = ($estado !== 'Aterrizado'); 
                            
                            $estado_class = '';
                            if ($estado === 'Aterrizado') $estado_class = 'status-aterrizado';
                            if ($estado === 'Retrasado') $estado_class = 'status-retrasado';
                            if ($estado === 'En Vuelo' || $estado === 'Programado') $estado_class = 'status-en-vuelo';
                        ?>
                            <tr>
                                <td data-label="ID Vuelo"><?php echo htmlspecialchars($vuelo['id_vuelo']); ?></td>
                                <td data-label="Aerolínea" class="airline-col"><?php echo htmlspecialchars($vuelo['aerolinea']); ?></td>
                                <td data-label="Origen"><?php echo htmlspecialchars($vuelo['origen']); ?></td>
                                <td data-label="Destino"><?php echo htmlspecialchars($vuelo['destino']); ?></td>
                                <td data-label="Hora de Llegada" class="time-col"><strong><?php echo htmlspecialchars($vuelo['hora_salida']); ?></strong></td>
                                <td data-label="Duración"><?php echo htmlspecialchars($vuelo['duracion']); ?></td>
                                <td data-label="Precio Normal"><?php echo format_price($vuelo['precio_normal']); ?></td>
                                <td data-label="Precio Business"><?php echo format_price($vuelo['precio_business']); ?></td>
                                <td data-label="Precio Primera"><?php echo format_price($vuelo['precio_primera']); ?></td>
                                <td data-label="Estado"><span class="status-badge <?php echo $estado_class; ?>"><?php echo $estado; ?></span></td>
                                <td data-label="Acción">
                                    <?php if ($can_buy): ?>
                                        <a href="formulario_reserva.php?id=<?php echo urlencode(trim($vuelo['id_vuelo'])); ?>" class="btn-select">Seleccionar</a>
                                    <?php else: ?>
                                        <span class="btn-disabled">No disponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            
            <div class="footer-col legal-matriz">
                <h4>AEROTEC Soluciones (Matriz)</h4>
                <p>AEROTEC Soluciones de Vuelo S.A. de C.V.</p>
                <p><strong>RFC:</strong> ASV231001XYZ (Ejemplo)</p>
                <p><strong>Dirección:</strong> Av. Aeroespacial #101, Ciudad de México, CDMX</p>
            </div>
            
            <div class="footer-col contact-info">
                <h4>Contacto y Soporte</h4>
                <p><strong>Email:</strong> <a href="mailto:soporte@aerotec.com" style="display:inline; color: var(--accent-color); text-decoration: none;">soporte@aerotec.com</a></p>
                <p><strong>Teléfono:</strong> +52 55 1234 5678</p>
                <p><strong>Horario:</strong> 9:00 a.m. - 6:00 p.m. (Lunes a Sábado)</p>
            </div>
            
            <div class="footer-col quick-links legal-links">
                <h4>Enlaces Legales</h4>
                <ul>
                    <li><a href="#">Política de Privacidad</a></li>
                    <li><a href="#">Términos de Servicio</a></li>
                    <li><a href="#">Aviso de Derechos Reservados</a></li>
                </ul>
            </div>
        </div>
        
        <div class="container" style="text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 10px; margin-top: 10px;">
            <p class="copyright-text">&copy; 2023 AEROTEC. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>