<?php

// ==============================================================================
// 2. LÓGICA DE PROCESAMIENTO: CARGA Y BÚSQUEDA DEL VUELO
// ==============================================================================
// CORRECCIÓN: Aseguramos la toma del ID desde POST (campo oculto) o GET (URL)
$vuelo_id = $_POST['id_vuelo'] 
            ?? $_GET['id'] 
            ?? null;
$vuelo_seleccionado = null;
$csv_file = 'vuelos.csv';

// Función para formatear el precio (Maneja valores nulos para evitar errores 'Deprecated')
function format_price($price) {
    if ($price === null || !is_numeric($price)) return '$0.00';
    return '$' . number_format($price, 2);
}

// Leer el archivo CSV para encontrar el vuelo seleccionado
if ($vuelo_id && ($handle = fopen($csv_file, 'r')) !== FALSE) {
    // Definición de las claves (asegúrate de que coincida con tu CSV)
    $keys = [
        'id_vuelo', 'aerolinea', 'origen', 'destino', 'fecha_salida', 
        'hora_salida', 'duracion', 'precio_normal', 'precio_business', 
        'precio_primera'
    ];
    
    // Ignorar el encabezado si existe
    $header = fgetcsv($handle, 1000, ',', '"', '\\'); 

    while (($data = fgetcsv($handle, 1000, ',', '"', '\\')) !== FALSE) {
        if (!empty($data) && count($data) >= count($keys)) {
            $vuelo = array_combine($keys, $data);
            
            // CORRECCIÓN CLAVE: Usar trim() para limpiar ambos IDs y solucionar la búsqueda
            if (trim($vuelo['id_vuelo']) === trim($vuelo_id)) { 
                // Limpieza y conversión de precios
                // Aseguramos que el reemplazo maneje miles si los hay (aunque number_format no los usa)
                $vuelo['precio_normal'] = floatval(str_replace(['$', ','], ['', ''], $vuelo['precio_normal']));
                $vuelo['precio_business'] = floatval(str_replace(['$', ','], ['', ''], $vuelo['precio_business']));
                $vuelo['precio_primera'] = floatval(str_replace(['$', ','], ['', ''], $vuelo['precio_primera']));
                
                $vuelo_seleccionado = $vuelo;
                break; // Vuelo encontrado, salir del bucle
            }
        }
    }
    fclose($handle);
}

// Bloque de redirección si el vuelo no se encuentra
if (!$vuelo_seleccionado) {
    // Esta redirección es una protección. En una aplicación real, no debería ocurrir si se selecciona desde vuelos_disponibles.
    header('Location: vuelos_disponibles.php?error=vuelo_no_encontrado');
    exit();
}


// ==============================================================================
// 3. CÁLCULO DE PRECIO TOTAL Y MANEJO DE FORMULARIO
// ==============================================================================
// Inicializar variables por defecto
$clase_seleccionada = $_POST['clase_seleccionada'] ?? 'normal'; 
$precio_unitario = 0;
$impuesto = 0;
$total_a_pagar = 0;
$opciones_clase = ['normal' => 'Normal ($0.00)'];

// PROTECCIÓN CLAVE: Solo procesar precios y opciones de clase si se encontró el vuelo
if ($vuelo_seleccionado) {
    switch ($clase_seleccionada) {
        case 'business':
            $precio_unitario = $vuelo_seleccionado['precio_business'];
            break;
        case 'primera':
            $precio_unitario = $vuelo_seleccionado['precio_primera'];
            break;
        case 'normal':
        default:
            $precio_unitario = $vuelo_seleccionado['precio_normal'];
            break;
    }

    // Asumimos un impuesto fijo (Ejemplo: 15%)
    $impuesto_rate = 0.15;
    // Redondeamos para evitar problemas de precisión en PHP (importante para la validación del backend)
    $impuesto = round($precio_unitario * $impuesto_rate, 2); 
    $total_a_pagar = round($precio_unitario + $impuesto, 2); 

    // Opciones para el <select> de clases (usando los datos reales)
    $opciones_clase = [
        'normal' => 'Normal (' . format_price($vuelo_seleccionado['precio_normal']) . ')',
        'business' => 'Business (' . format_price($vuelo_seleccionado['precio_business']) . ')',
        'primera' => 'Primera (' . format_price($vuelo_seleccionado['precio_primera']) . ')',
    ];
}

// Configuración del menú dinámico
$auth_link = '<li><a href="logout.php" class="btn-logout">Cerrar Sesión</a></li>';

// Función auxiliar para obtener datos de vuelo de forma segura
function get_flight_data($vuelo, $key, $default = 'N/A') {
    return htmlspecialchars($vuelo[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEROTEC - Reserva de Vuelo</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="images/LOGO.JPG" type="image/jpeg">
    <style>
        /* Estilos del Formulario y Resumen */
        .reservation-container {
            display: flex;
            gap: 30px;
            padding: 40px 0;
            align-items: flex-start;
        }

        .form-section {
            flex: 2;
            background-color: var(--card-bg-color);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .summary-panel {
            flex: 1;
        }

        .card-resumen-vuelo, .card-total-pago {
            background-color: var(--primary-color);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        
        .card-resumen-vuelo p, .card-total-pago p {
            margin: 0;
            padding: 5px 0;
            display: flex;
            justify-content: space-between;
            font-size: 1.1em;
        }
        
        .card-resumen-vuelo .value {
            font-weight: 700;
            color: var(--accent-color);
        }
        
        .card-total-pago h3 {
            text-align: center;
            color: white;
            margin-top: 0;
            font-size: 1.8em;
        }
        
        .card-total-pago .total-amount {
            font-size: 3em;
            font-weight: 900;
            text-align: center;
            color: var(--secondary-color);
            margin: 10px 0;
        }

        /* Estilos del Formulario */
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
        }

        input[type="text"], input[type="email"], input[type="tel"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
        }

        .alert-info {
            background-color: #ffe0b2; /* Tono naranja suave para la alerta */
            color: #333;
            padding: 15px;
            border-left: 5px solid #ff9800;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95em;
        }

        .btn-submit {
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 700;
            margin-top: 25px;
            width: 100%;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: var(--hover-color);
        }
        
        /* Estilos para el botón de Cerrar Sesión en el header */
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
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="vuelos_disponibles.php"class="active">Comprar boleto</a></li>
                    <li><a href="vuelos_tiempo_real.php">Vuelos en Tiempo Real</a></li>
                    <li><a href="vuelos_reservados.php">Vuelos Reservados</a></li>
                    <?php echo $auth_link; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="flight-page">
        <div class="container">
            
            <h2 style="margin-top: 0;">Reserva de Vuelo y Datos de Pasajero</h2>
            <?php if ($vuelo_seleccionado): ?>
                <p>Complete sus datos generales para confirmar su asiento en la ruta **<?php echo get_flight_data($vuelo_seleccionado, 'origen'); ?> &rarr; <?php echo get_flight_data($vuelo_seleccionado, 'destino'); ?>**:</p>
            <?php else: ?>
                   <p>No se ha podido cargar la información del vuelo. Por favor, regrese a <a href="vuelos_disponibles.php">Vuelos Disponibles</a> y seleccione uno.</p>
            <?php endif; ?>
            
            <div class="reservation-container">
                
                <div class="form-section">
                    <h3>Datos del Pasajero</h3>
                    
                    <div class="alert-info">
                        <p>ℹ️ **Importante:** Por favor, ingrese sus datos **exactamente** como aparecen en su **Identificación Oficial** (Pasaporte, INE/DNI, Cédula), ya que estos serán utilizados para generar su pase de abordar.</p>
                    </div>
                    
                    <!-- El action por defecto apunta a sí mismo para el recálculo (onchange) -->
                    <form method="POST" id="reservaForm" action="formulario_reserva.php?id=<?php echo htmlspecialchars($vuelo_id ?? ''); ?>">
                        
                        <!-- DATOS DEL FORMULARIO -->
                        <input type="hidden" name="id_vuelo" value="<?php echo get_flight_data($vuelo_seleccionado, 'id_vuelo'); ?>">
                        
                        <label for="nombre">Nombre(s) Completo(s):</label>
                        <!-- CORRECCIÓN: Se añade autocomplete="off" para prevenir que el navegador autocompleta con datos no deseados -->
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required autocomplete="off">
                        
                        <label for="apellido_paterno">Apellido Paterno:</label>
                        <!-- CORRECCIÓN: Se añade autocomplete="off" -->
                        <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?php echo htmlspecialchars($_POST['apellido_paterno'] ?? ''); ?>" required autocomplete="off">
                        
                        <label for="apellido_materno">Apellido Materno:</label>
                        <!-- CORRECCIÓN: Se añade autocomplete="off" -->
                        <input type="text" id="apellido_materno" name="apellido_materno" value="<?php echo htmlspecialchars($_POST['apellido_materno'] ?? ''); ?>" required autocomplete="off">
                        
                        <label for="correo">Correo Electrónico:</label>
                        <!-- CORRECCIÓN: Se añade autocomplete="off" para prevenir que el navegador autocompleta con datos no deseados -->
                        <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required autocomplete="off">
                        
                        <label for="telefono">Teléfono de Contacto:</label>
                        <!-- CORRECCIÓN: Se añade autocomplete="off" -->
                        <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>" required autocomplete="off">
                        
                        <label for="clase_seleccionada">Selección de Clase:</label>
                        <!-- onchange hace un submit que apunta a sí mismo (urlRecalculo) -->
                        <select id="clase_seleccionada" name="clase_seleccionada" onchange="this.form.submit()">
                            <?php foreach ($opciones_clase as $valor => $etiqueta): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($valor); ?>" 
                                    <?php if ($clase_seleccionada === $valor) echo 'selected'; ?>
                                >
                                    <?php echo htmlspecialchars($etiqueta); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" id="btnPagar" class="btn-submit" name="confirmar_reserva">Confirmar Reserva </button> 
                        
                        <!-- DATOS OCULTOS DE VUELO CRÍTICOS PARA procesar_reserva.php -->
                        <!-- 
                            CRÍTICO: Estos campos OCULTOS aseguran que los datos del vuelo 
                            (origen, destino, precio, etc.) se envíen al script de procesamiento (procesar_reserva.php)
                            cuando el usuario hace clic en "Confirmar Reserva".
                        -->
                        <input type="hidden" name="total_a_pagar" value="<?php echo htmlspecialchars($total_a_pagar); ?>">
                        <input type="hidden" name="vuelo_id" value="<?php echo get_flight_data($vuelo_seleccionado, 'id_vuelo'); ?>">
                        <input type="hidden" name="origen" value="<?php echo get_flight_data($vuelo_seleccionado, 'origen'); ?>">
                        <input type="hidden" name="destino" value="<?php echo get_flight_data($vuelo_seleccionado, 'destino'); ?>">
                        <input type="hidden" name="fecha_salida" value="<?php echo get_flight_data($vuelo_seleccionado, 'fecha_salida'); ?>">
                        <input type="hidden" name="hora_salida" value="<?php echo get_flight_data($vuelo_seleccionado, 'hora_salida'); ?>">
                        <!-- 
                            CRÍTICO: Se añade un campo con el nombre completo para que 
                            procesar_reserva.php lo lea como 'pasajero' y no use el valor simulado.
                        -->
                        <input type="hidden" id="nombre_completo" name="pasajero" value="">
                        
                    </form>
                </div>
                
                <div class="summary-panel">
                    
                    <h3 style="margin-top: 0;">Resumen de Viaje</h3>
                    
                    <div class="card-resumen-vuelo">
                        <?php if ($vuelo_seleccionado): ?>
                            <p>Vuelo: <span class="value"><?php echo get_flight_data($vuelo_seleccionado, 'id_vuelo'); ?> &ndash; <?php echo get_flight_data($vuelo_seleccionado, 'origen'); ?> / <?php echo get_flight_data($vuelo_seleccionado, 'destino'); ?></span></p>
                            <p>Clase: <span class="value"><?php echo htmlspecialchars(ucfirst($clase_seleccionada)); ?></span></p>
                            <hr style="border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 10px 0;">
                            <p>Hora de Salida: <span class="value"><?php echo get_flight_data($vuelo_seleccionado, 'hora_salida'); ?></span></p>
                            <p>Duración: <span class="value"><?php echo get_flight_data($vuelo_seleccionado, 'duracion'); ?></span></p>
                        <?php else: ?>
                            <p style="text-align: center;">Información de vuelo no disponible.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card-total-pago">
                        <h3>Total a Pagar</h3>
                        <p class="total-amount"><?php echo format_price($total_a_pagar); ?></p>
                        <p style="text-align: center; font-size: 0.9em;">* Impuestos (<?php echo format_price($impuesto); ?>) y cargos incluidos.</p>
                    </div>
                </div>
                
            </div>
            <p style="text-align: right; color: var(--light-text-color);">Al confirmar, se generará su boleto y se restarán los asientos de la base de datos.</p>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            </div>
        <div class="container" style="text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 10px; margin-top: 10px;">
            <p class="copyright-text">&copy; 2023 AEROTEC. Todos los derechos reservados.</p>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('reservaForm');
            const selectClase = document.getElementById('clase_seleccionada');
            const btnPagar = document.getElementById('btnPagar');

            // Campos de nombre y apellido
            const inputNombre = document.getElementById('nombre');
            const inputAP = document.getElementById('apellido_paterno');
            const inputAM = document.getElementById('apellido_materno');
            const inputNombreCompleto = document.getElementById('nombre_completo');

            // URL original de la página para actualizar el precio
            const urlRecalculo = 'formulario_reserva.php?id=<?php echo htmlspecialchars($vuelo_id ?? ''); ?>';
            
            // URL de procesamiento final base
            const urlProcesamiento = 'procesar_reserva.php'; 

            // Función para actualizar el campo oculto con el nombre completo
            function actualizarNombreCompleto() {
                const nombre = inputNombre.value.trim();
                const ap = inputAP.value.trim();
                const am = inputAM.value.trim();
                // Formato: Nombre(s) ApellidoPaterno ApellidoMaterno (se asume que 'pasajero' requiere este formato)
                const nombreCompleto = `${nombre} ${ap} ${am}`.trim();
                inputNombreCompleto.value = nombreCompleto;
            }

            // Escuchar cambios en los campos de nombre/apellido para preparar el campo oculto 'pasajero'
            if (inputNombre) inputNombre.addEventListener('input', actualizarNombreCompleto);
            if (inputAP) inputAP.addEventListener('input', actualizarNombreCompleto);
            if (inputAM) inputAM.addEventListener('input', actualizarNombreCompleto);
            
            // Inicializar el campo oculto si ya hay valores en el formulario (p. ej., después de un submit para recálculo)
            actualizarNombreCompleto();


            // 1. Manejo del cambio de clase (sigue enviando a sí mismo)
            if (selectClase) {
                // Se mueve la lógica de submit al onchange directamente en el HTML del <select>
                // selectClase.onchange = function() {
                //     // Importante: No cambiamos la acción aquí, solo hacemos submit para recálculo
                //     form.action = urlRecalculo;
                //     form.submit();
                // };
            }

            // 2. Manejo del botón de pagar 
            if (btnPagar) {
                btnPagar.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aseguramos que el campo oculto 'pasajero' tenga el valor final antes de validar
                    actualizarNombreCompleto(); 

                    if (!form.checkValidity()) {
                        form.reportValidity();
                        return;
                    }

                    // 🔥 CORRECCIÓN CLAVE: OBLIGAMOS a cambiar la acción al script de procesamiento
                    const idVueloInput = document.querySelector('input[name="id_vuelo"]');
                    const idVuelo = idVueloInput ? idVueloInput.value : '';

                    // Cambiamos la acción para que apunte a procesar_reserva.php
                    form.action = urlProcesamiento + "?id=" + encodeURIComponent(idVuelo);
                    
                    // Envía el formulario (datos de pasajero, clase, precio total + datos ocultos de vuelo) por POST
                    form.submit();
                });
            }
        });
    </script>
</body>
</html>