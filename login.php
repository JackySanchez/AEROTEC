<?php
// CRÍTICO: Iniciar Output Buffering para asegurar que las redirecciones (headers) funcionen
// incluso si hay espacios en blanco o texto antes de session_start().
ob_start(); 

// ======================================================================
// login.php - Versión Unificada de Login y Registro (AEROTEC)
// ======================================================================

// Iniciar sesión (necesario para la redirección después del login)
session_start();

// --- VERIFICACIÓN DE AUTENTICACIÓN ---
// Si ya está autenticado, redirige inmediatamente a la página de Vuelos
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: vuelos_disponibles.php'); // Redirección adaptada a AEROTEC
    exit();
}

$mensaje_error = '';
$mensaje_exito = '';
$usuarios_file = 'usuarios.json';
$input_usuario = ''; // Mantener el usuario en el campo si hay error
$current_mode = 'login'; // Modo por defecto es 'login'
$error_fields = []; // Para resaltar campos con errores

// --- FUNCIONES NECESARIAS ---

// Simulación de log_action 
function log_action($message) {
    $log_file = 'acciones.log';
    $time = date('Y-m-d H:i:s');
    // fwrite(fopen($log_file, 'a'), "[$time] $message" . PHP_EOL); 
}

/** Carga los usuarios (con manejo de creación por defecto) */
function cargar_usuarios() {
    global $usuarios_file;
    if (!file_exists($usuarios_file)) {
        // Crear el archivo con un usuario por defecto hasheado
        $default_users = [
            'Admin' => password_hash('aerotec2025', PASSWORD_DEFAULT), // Contraseña adaptada
            'Prueba' => password_hash('12345678', PASSWORD_DEFAULT) 
        ]; 
        guardar_usuarios($default_users);
        return $default_users;
    }
    $json_data = @file_get_contents($usuarios_file);
    return json_decode($json_data, true) ?: [];
}

/** Guarda los usuarios */
function guardar_usuarios(array $usuarios) {
    global $usuarios_file;
    // Guardamos el array tal cual, respetando la capitalización de las claves (nombres de usuario)
    file_put_contents($usuarios_file, json_encode($usuarios, JSON_PRETTY_PRINT));
}

// ----------------------------------------------------------------------
// --- LÓGICA PRINCIPAL ---
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuarios = cargar_usuarios();
    // Obtener el modo de la solicitud POST.
    $current_mode = $_POST['mode'] ?? 'login'; 

    // Obtener y sanitizar entradas.
    $input_usuario = trim(filter_input(INPUT_POST, 'usuario') ?? '');
    $input_password = filter_input(INPUT_POST, 'password');
    $input_confirm_password = filter_input(INPUT_POST, 'confirm_password');

    // Validación general de campos vacíos
    if (empty($input_usuario) || empty($input_password)) {
        $mensaje_error = 'El usuario y la contraseña no pueden estar vacíos.';
        if (empty($input_usuario)) $error_fields[] = 'usuario';
        if (empty($input_password)) $error_fields[] = 'password';
    } elseif ($current_mode === 'register' && empty($input_confirm_password)) {
        $mensaje_error = 'Por favor, confirma tu contraseña.';
        $error_fields[] = 'confirm_password';
    }

    // ===================================
    // 1. LÓGICA DE REGISTRO
    // ===================================
    elseif ($current_mode === 'register') {
        if ($input_password !== $input_confirm_password) {
            $mensaje_error = 'Las contraseñas no coinciden.';
            $error_fields[] = 'password';
            $error_fields[] = 'confirm_password';
        } elseif (isset($usuarios[$input_usuario])) {
            $mensaje_error = 'El nombre de usuario ya existe.';
            $error_fields[] = 'usuario';
        } elseif (strlen($input_password) < 8) {
            $mensaje_error = 'La contraseña debe tener al menos 8 caracteres.';
            $error_fields[] = 'password';
            $error_fields[] = 'confirm_password';
        } else {
            // Guardar usuario tal cual se ingresó (respetando mayúsculas/minúsculas)
            $usuarios[$input_usuario] = password_hash($input_password, PASSWORD_DEFAULT);
            guardar_usuarios($usuarios);
            $mensaje_exito = "🎉 ¡Registro exitoso! Ahora puedes iniciar sesión como **" . htmlspecialchars($input_usuario) . "**.";
            log_action("NUEVO USUARIO REGISTRADO: " . $input_usuario);
            $input_usuario = ''; // Limpiar el campo usuario tras éxito
            $current_mode = 'login'; // Después del registro exitoso, cambiamos a modo login
        }
    } 
    
    // ===================================
    // 2. LÓGICA DE LOGIN
    // ===================================
    elseif ($current_mode === 'login') {
        if (isset($usuarios[$input_usuario]) && password_verify($input_password, $usuarios[$input_usuario])) {
            // Regenerar ID de sesión para prevenir Session Fixation
            session_regenerate_id(true); 
            
            $_SESSION['autenticado'] = true;
            $_SESSION['usuario'] = $input_usuario; // Guardamos el nombre exacto en la sesión
            log_action("INICIO DE SESIÓN EXITOSO: " . $input_usuario);
            
            // --------------------------------------------------------
            // CRÍTICO: Redirigir a la página de Vuelos Disponibles
            // --------------------------------------------------------
            header('Location: vuelos_disponibles.php'); // <--- REDIRECCIÓN CLAVE
            exit(); 
        } else {
            // MENSAJE DE ERROR MÁS ESPECÍFICO
            $mensaje_error = '❌ Usuario o Contraseña incorrectos. Por favor, verifica tus datos.';
            log_action("INICIO DE SESIÓN FALLIDO para usuario: " . $input_usuario);
            $current_mode = 'login'; // Asegurarse de que el formulario se mantenga en modo login
            $error_fields[] = 'usuario';
            $error_fields[] = 'password';
        }
    }
}

// Determinar qué campos mostrar y qué texto de botón y enlace usar
$is_register_mode = ($current_mode === 'register');
$main_button_text = $is_register_mode ? 'REGISTRAR' : 'ENTRAR';
$toggle_link_text = $is_register_mode ? '¿Ya tienes cuenta? Iniciar Sesión' : '¿No tienes cuenta? Registrar';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>AEROTEC - Acceso</title>
    <link rel="stylesheet" href="styles.css"> 
    
    <style>
        /* --- ESTILOS PARA COINCIDIR CON EL DISEÑO MINIMALISTA --- */
        
        body {
            /* Fondo azul oscuro adaptado a los colores de AEROTEC (púrpura o un color neutro) */
            background-color: #f4f4f4; /* Fondo claro para contraste */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; 
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 380px;
            background: white;
            padding: 40px;
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); /* Sombra ligera y moderna */
            text-align: center;
        }
        
        .login-logo {
            width: 80px; 
            height: auto;
            margin-bottom: 10px;
        }

        .login-container h2 {
            color: var(--primary-color, #7A00FF); /* Color principal de AEROTEC */
            font-size: 1.8em;
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* Campos de entrada */
        .login-container input[type="text"], 
        .login-container input[type="password"] {
            width: 100%; 
            padding: 14px;
            margin-bottom: 20px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        /* Estilo de error para campos */
        .login-container input.error {
             border-color: #C62828 !important; 
        }
        .login-container input:focus {
             border-color: var(--primary-color, #7A00FF);
             outline: none;
        }

        /* Botón principal (ENTRAR/REGISTRAR) */
        .btn-main { 
            width: 100%;
            padding: 14px 0; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold;
            transition: 0.2s ease;
            text-transform: uppercase;
            margin-bottom: 15px; 
            
            /* Colores dinámicos */
            background-color: <?php echo $is_register_mode ? 'var(--secondary-color, #00C6FF)' : 'var(--primary-color, #7A00FF)'; ?>;
            color: white; 
            box-shadow: 0 4px 6px rgba(122, 0, 255, 0.4); 
        }
        .btn-main:hover {
            background-color: <?php echo $is_register_mode ? 'var(--secondary-color-dark, #00A3D2)' : 'var(--primary-color-dark, #5F00C8)'; ?>;
            box-shadow: 0 3px 5px rgba(95, 0, 200, 0.6);
        }

        /* Enlace de alternancia (Registrar/Iniciar Sesión) */
        .toggle-link-group {
            font-size: 0.9em;
            color: #616161;
        }
        .toggle-link {
            color: var(--primary-color, #7A00FF); 
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
            cursor: pointer;
        }
        .toggle-link:hover {
            color: var(--primary-color-dark, #5F00C8);
            text-decoration: underline;
        }

        /* Mensajes de estado (mantenidos) */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: left;
        }
        .error-message {
            background-color: #FFCDD2; 
            color: #C62828; 
            border-left: 6px solid #C62828;
        }
        .success-message {
            background-color: #C8E6C9; 
            color: #2E7D32; 
            border-left: 6px solid #2E7D32;
        }
    </style>
    
    <script>
        // Función para cambiar el modo del formulario (Login/Registro)
        function toggleMode(mode) {
            const modeInput = document.getElementById('mode-input');
            const confirmPasswordField = document.getElementById('confirm-password-field');
            const mainButton = document.getElementById('main-button');
            const toggleLinkGroup = document.getElementById('toggle-link-group');
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#7A00FF';
            const secondaryColor = getComputedStyle(document.documentElement).getPropertyValue('--secondary-color') || '#00C6FF';
            const primaryColorDark = getComputedStyle(document.documentElement).getPropertyValue('--primary-color-dark') || '#5F00C8';
            const secondaryColorDark = getComputedStyle(document.documentElement).getPropertyValue('--secondary-color-dark') || '#00A3D2';

            modeInput.value = mode;

            if (mode === 'register') {
                confirmPasswordField.style.display = 'block'; // Mostrar Confirmar Contraseña
                confirmPasswordField.setAttribute('required', 'required'); // Hacer el campo requerido
                mainButton.innerText = 'REGISTRAR';
                mainButton.style.backgroundColor = secondaryColor; 
                mainButton.onmouseover = () => mainButton.style.backgroundColor = secondaryColorDark;
                mainButton.onmouseout = () => mainButton.style.backgroundColor = secondaryColor;
                toggleLinkGroup.innerHTML = '¿Ya tienes cuenta? <a id="toggle-link" class="toggle-link" onclick="toggleMode(\'login\')">Iniciar Sesión</a>';
                document.getElementById('form-title').innerText = "Registro de Usuario";
            } else { // mode === 'login'
                confirmPasswordField.style.display = 'none'; // Ocultar Confirmar Contraseña
                confirmPasswordField.removeAttribute('required'); // Quitar el campo requerido
                mainButton.innerText = 'ENTRAR';
                mainButton.style.backgroundColor = primaryColor;
                mainButton.onmouseover = () => mainButton.style.backgroundColor = primaryColorDark;
                mainButton.onmouseout = () => mainButton.style.backgroundColor = primaryColor;
                toggleLinkGroup.innerHTML = '¿No tienes cuenta? <a id="toggle-link" class="toggle-link" onclick="toggleMode(\'register\')">Registrar</a>';
                document.getElementById('form-title').innerText = "AEROTEC";
            }
        }

        // Llamar a toggleMode al cargar la página para establecer el estado inicial
        document.addEventListener('DOMContentLoaded', () => {
            // El modo inicial lo establece PHP al procesar el POST o por defecto (login)
            const initialMode = "<?php echo $current_mode; ?>";
            toggleMode(initialMode);
            
            // Si hay errores, forzamos la apariencia de los campos con error
            const errorFields = <?php echo json_encode($error_fields); ?>;
            errorFields.forEach(field => {
                const input = document.querySelector(`input[name="${field}"]`);
                if (input) {
                    input.classList.add('error');
                    input.addEventListener('focus', () => input.classList.remove('error'), { once: true });
                }
            });
        });
    </script>
</head>
<body>
    <div class="login-container">
        
        <img src="images/LOGO.jpg" alt="Logo AEROTEC" class="login-logo" />

        <h2 id="form-title">AEROTEC</h2>
        
        <?php if ($mensaje_error): ?>
            <div class="message error-message">
                <?php echo $mensaje_error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($mensaje_exito): ?>
            <div class="message success-message">
                <?php echo $mensaje_exito; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="login-register-form">
            <input type="hidden" name="mode" id="mode-input" value="<?php echo $current_mode; ?>">
            
            <input type="text" 
                    name="usuario" 
                    placeholder="Usuario" 
                    required 
                    value="<?php echo htmlspecialchars($input_usuario); ?>">
                    
            <input type="password" 
                    name="password" 
                    placeholder="Contraseña" 
                    required>
            
            <input type="password" 
                    name="confirm_password" 
                    id="confirm-password-field"
                    placeholder="Confirmar Contraseña (Min. 8)">
                    
            <button type="submit" id="main-button" class="btn-main">
                <?php echo $main_button_text; ?>
            </button>
            
            <div class="toggle-link-group" id="toggle-link-group">
                <a id="toggle-link" class="toggle-link" onclick="toggleMode('<?php echo $is_register_mode ? 'login' : 'register'; ?>')">
                    <?php echo $toggle_link_text; ?>
                </a>
            </div>
        </form>
    </div>
</body>
</html>
<?php 
// CRÍTICO: Aseguramos que todo el contenido en búfer se envíe al final.
ob_end_flush(); 
?>