<?php
// ==============================================================================
// 1. SIMULACIÓN DE DATOS DEL BOLETO
//    En un sistema real, estos datos vendrían de la confirmación de la reserva.
// ==============================================================================

// Datos de la reserva simulada
$boleto_data = [
    'id_reserva' => 'R-20251125-9003',
    'pasajero' => 'Laura M. García',
    'vuelo_id' => 'AR-301',
    'aerolinea' => 'AEROTEC',
    'origen' => 'Cancún (CUN)',
    'destino' => 'Ciudad de México (CDMX)',
    'fecha_salida' => '2025-12-15',
    'hora_llegada' => '08:30',
    'clase' => 'Business',
    'asiento' => '12A',
    'precio' => 1500.00,
    'puerta' => 'A5',
    'embarque' => '07:45',
];

// Función para obtener el precio formateado
function format_price($price) {
    return '$' . number_format($price, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Boleto - AEROTEC</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* Estilos específicos para el diseño del boleto (Reporte PDF) */
        .ticket-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: var(--card-bg-color);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 3px solid var(--secondary-color);
        }

        .ticket-header {
            background-color: var(--primary-color);
            color: white;
            padding: 25px 35px;
            text-align: center;
        }

        .ticket-header h2 {
            margin: 0;
            font-size: 2.2em;
            color: var(--accent-color);
        }

        .ticket-details {
            display: flex;
            padding: 30px 35px;
            border-bottom: 2px dashed #ddd;
            flex-wrap: wrap;
        }

        .detail-item {
            flex: 1 1 33%; /* 3 items por fila en escritorio */
            min-width: 150px;
            margin-bottom: 20px;
        }

        .detail-label {
            display: block;
            font-size: 0.9em;
            color: var(--light-text-color);
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1.3em;
            color: var(--dark-text-color);
            font-weight: 700;
        }

        /* Estilos de información destacada */
        .route-info {
            background-color: var(--main-bg-color);
            padding: 20px 35px;
            text-align: center;
        }

        .route-info .detail-value {
            font-size: 2.5em;
            color: var(--secondary-color);
        }

        .route-info i {
            margin: 0 15px;
            color: var(--primary-color);
            font-size: 1.8em;
        }
        
        /* Sección de descarga */
        .ticket-footer {
            padding: 30px 35px;
            text-align: center;
            background-color: #fafafa;
        }

        /* Media Query para responsividad del boleto */
        @media (max-width: 600px) {
            .ticket-details {
                flex-direction: column;
            }
            .detail-item {
                flex: 1 1 100%;
                text-align: center;
            }
            .route-info i {
                display: block;
                margin: 10px 0;
            }
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
                    <li><a href="index.html">Inicio</a></li>
                    <li><a href="vuelos_disponibles.php">Vuelos Disponibles</a></li>
                    <li><a href="vuelos_tiempo_real.php">Vuelos en Tiempo Real</a></li>
                    <li><a href="vuelos_reservados.php">Vuelos Reservados</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="flight-page">
        <div class="container">
            <h2><i class="fas fa-file-pdf" style="color: var(--secondary-color);"></i> Boleto Electrónico</h2>
            <p>Tu reserva ha sido confirmada. Descarga a continuación tu pase de abordar.</p>

            <div class="ticket-container">
                <div class="ticket-header">
                    <h2>PASE DE ABORDAR | AEROTEC</h2>
                    <p style="margin: 5px 0 0; font-size: 1.1em; opacity: 0.9;">Reserva #<?= $boleto_data['id_reserva'] ?></p>
                </div>

                <div class="route-info">
                    <span class="detail-label">Ruta de Vuelo</span>
                    <div class="detail-value">
                        <?= $boleto_data['origen'] ?> <i class="fas fa-plane"></i> <?= $boleto_data['destino'] ?>
                    </div>
                </div>

                <div class="ticket-details">
                    <div class="detail-item">
                        <span class="detail-label">Pasajero</span>
                        <span class="detail-value"><?= $boleto_data['pasajero'] ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Vuelo y Aerolínea</span>
                        <span class="detail-value"><?= $boleto_data['vuelo_id'] ?> (<?= $boleto_data['aerolinea'] ?>)</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de Salida</span>
                        <span class="detail-value"><?= $boleto_data['fecha_salida'] ?></span>
                    </div>
                </div>

                <div class="ticket-details" style="border-bottom: none;">
                    <div class="detail-item">
                        <span class="detail-label">Hora de Llegada</span>
                        <span class="detail-value time-col" style="color: var(--secondary-color);"><?= $boleto_data['hora_llegada'] ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Puerta de Embarque</span>
                        <span class="detail-value" style="font-size: 1.5em; color: var(--primary-color);"><?= $boleto_data['puerta'] ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Embarque Inicia</span>
                        <span class="detail-value"><?= $boleto_data['embarque'] ?></span>
                    </div>
                </div>
                
                <div class="ticket-details" style="background-color: #fcfcfc;">
                    <div class="detail-item">
                        <span class="detail-label">Clase</span>
                        <span class="detail-value"><?= $boleto_data['clase'] ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Asiento</span>
                        <span class="detail-value"><?= $boleto_data['asiento'] ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Precio Total</span>
                        <span class="detail-value" style="color: #4CAF50;"><?= format_price($boleto_data['precio']) ?></span>
                    </div>
                </div>

                <div class="ticket-footer">
                    <p style="color: var(--dark-text-color); margin-bottom: 20px;">
                        Para obtener tu boleto oficial en formato imprimible, haz clic en el botón.
                    </p>
                    <a href="download_boleto.php?id=<?= $boleto_data['id_reserva'] ?>" class="btn-select primary-btn" style="padding: 15px 30px; font-size: 1.2em;">
                        <i class="fas fa-download"></i> DESCARGAR BOLETO PDF
                    </a>
                </div>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container footer-content">
            <div class="footer-col">
                <h4>Contacto</h4>
                <p>Dirección: Av. Principal #905, Ciudad de México</p>
                <p>Teléfono: +52 55 1234 5678</p>
                <p>Email: soporte@aerotec.com</p>
            </div>
            <div class="footer-col">
                <h4>Enlaces Rápidos</h4>
                <ul>
                    <li><a href="vuelos_disponibles.php">Buscar Vuelo</a></li>
                    <li><a href="vuelos_reservados.php">Mis Reservas</a></li>
                    <li><a href="generar_boleto.php">Reporte de Vuelos</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Acerca de AEROTEC</h4>
                <p>Innovación en la gestión de vuelos y reservas.</p>
            </div>
        </div>
        <div class="container" style="text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px;">
            <p class="copyright-text">© 2023 AEROTEC. Todos los derechos reservados.</p>
        </div>
    </footer>

</body>
</html>