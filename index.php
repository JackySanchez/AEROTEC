<?php
// Iniciar sesión para verificar el estado de autenticación
session_start();

// Determinar el estado de autenticación
$autenticado = isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;
// Ya no necesitamos $nombre_usuario para mostrar en el menú.

// Definir los enlaces de autenticación
if ($autenticado) {
    // Si está autenticado, solo mostramos el enlace de Cerrar Sesión
    // NOTA: Eliminamos la línea del 'welcome-text'
    $auth_link = '<li><a href="logout.php" class="btn-logout">Cerrar Sesión</a></li>';
} else {
    // Si no está autenticado, mostramos el enlace de Iniciar Sesión
    $auth_link = '<li><a href="login.php" class="btn-login">Iniciar Sesión</a></li>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEROTEC - Vuelos y Experiencias</title>
    
    <link rel="stylesheet" href="styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="icon" href="images/LOGO.JPG" type="image/jpeg">
    
    <style>
        /* Agregamos estilos para los nuevos elementos de sesión */
        /* Eliminamos .welcome-text ya que ya no se usa en el menú */
        
        .btn-logout {
            background-color: var(--accent-color, #ff4500); /* Color de acento para que resalte */
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .btn-logout:hover {
            background-color: #cc3700;
        }
        .btn-login {
            background-color: var(--secondary-color, #00c6ff);
            color: var(--dark-text-color, #000);
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .btn-login:hover {
            background-color: #0099cc;
        }

        /* Estilos específicos para la sección de inicio */
        .hero-section {
            /* Usamos un fondo que simboliza viaje */
            background: linear-gradient(rgba(0, 0, 0, 0.65), rgba(0, 0, 0, 0.65)), url('images/landing_bg.jpg') no-repeat center center;
            background-size: cover;
            height: 60vh; /* Altura más grande para la bienvenida */
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }

        .hero-content h1 {
            font-size: 4.5em;
            margin-bottom: 5px;
            font-weight: 900;
            color: var(--accent-color); /* Color de acento para el título */
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.7);
        }

        .hero-content p {
            font-size: 1.8em;
            margin-bottom: 40px;
            font-weight: 300;
        }

        /* Sección de Promoción de la Empresa */
        .promo-section {
            padding: 40px 0;
            background-color: var(--card-bg-color); /* Fondo blanco para el texto */
        }

        .promo-section h2 {
            font-size: 2.2em;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 15px;
        }

        .promo-text {
            max-width: 800px;
            margin: 0 auto 30px;
            text-align: center;
            font-size: 1.1em;
            color: var(--light-text-color);
        }

        /* Contenedor de Botones (Tarjetas de Acción) */
        .action-cards {
            display: flex;
            gap: 25px;
            margin-top: 40px;
            margin-bottom: 50px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-card {
            background-color: var(--main-bg-color);
            border: 1px solid #ddd;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            flex: 1;
            min-width: 250px;
            max-width: 320px;
            text-align: center;
            transition: all 0.3s;
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(138, 43, 226, 0.3);
            border-color: var(--secondary-color);
        }

        .action-card h3 {
            color: var(--dark-text-color);
            font-size: 1.4em;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 10px;
            margin-bottom: 15px;
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
                    <li><a href="index.php" class="active">Inicio</a></li>
                    <li><a href="vuelos_disponibles.php">Vuelos Disponibles</a></li>
                    <li><a href="vuelos_tiempo_real.php">Vuelos en Tiempo Real</a></li>
                    <li><a href="vuelos_reservados.php">Vuelos Reservados</a></li>
                    
                    <?php echo $auth_link; ?>

                </ul>
            </nav>
        </div>
    </header>

    <div class="hero-section">
        <div class="hero-content">
            <?php if ($autenticado): ?>
                <h1>¡Bienvenido a Bordo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
                <p>Explora nuestras opciones de vuelo y reserva tu próxima aventura.</p>
            <?php else: ?>
                <h1>Viaja con AEROTEC. Tu Destino te Espera.</h1>
                <p>La mejor tecnología y comodidad en cada vuelo. ¡Inicia sesión para reservar!</p>
            <?php endif; ?>

            <a href="vuelos_disponibles.php" class="btn-select large-btn" style="width: auto; padding: 15px 30px; font-size: 1.3em;">Buscar Vuelos Ahora</a>
        </div>
    </div>
    
    <main class="flight-page">
        <div class="container">
            
            <section class="promo-section">
                <h2>☁️ Excelencia Aérea y Compromiso</h2>
                <p class="promo-text">
                    En AEROTEC, combinamos la innovación tecnológica con un servicio al cliente excepcional para ofrecerte una experiencia de vuelo sin igual. Nuestro sistema de gestión está diseñado para la rapidez, seguridad y transparencia, asegurando que tu proceso de reserva sea tan suave como tu aterrizaje. **¡Explora nuestros servicios y toma el control de tu viaje!**
                </p>
                <p class="promo-text">
                    Estamos comprometidos con el cumplimiento de la **Rúbrica de Estructura de Datos** utilizando algoritmos de ordenamiento eficientes, manejo de archivos estructurados y generación de reportes PDF para ofrecerte la máxima calidad y confiabilidad en la información.
                </p>
            </section>

            <section class="action-cards-section">
                <h2 style="text-align: center; color: var(--secondary-color); margin-bottom: 35px;">Servicios Rápidos</h2>
                
                <div class="action-cards">
                    
                    <div class="action-card">
                        <h3>Vuelos Disponibles</h3>
                        <p>Encuentra tu próximo vuelo y consulta el precio y disponibilidad de asientos.</p>
                        <a href="vuelos_disponibles.php" class="btn-select" style="margin-top: 15px;">Ir a Consultar</a>
                    </div>
                    
                    <div class="action-card">
                        <h3>Vuelos Reservados</h3>
                        <p>Revisa y gestiona tus reservas pendientes y obtén tu confirmación.</p>
                        <a href="vuelos_reservados.php" class="btn-select" style="margin-top: 15px;">Ver mis Reservas</a>
                    </div>
                    
                    <div class="action-card">
                        <h3>Vuelos en Tiempo Real</h3>
                        <p>Monitorea la ubicación y el estado actual de los vuelos en el aire.</p>
                        <a href="vuelos_tiempo_real.php" class="btn-select" style="margin-top: 15px;">Ver Estado</a>
                    </div>

                </div>
            </section>
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