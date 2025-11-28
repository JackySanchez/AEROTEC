<?php
// CRÍTICO: Iniciar Output Buffering para asegurar que las redirecciones (headers) funcionen
ob_start(); 

session_start();

// 1. Destruir todas las variables de sesión
$_SESSION = array();

// 2. Si se utiliza cookies de sesión, destrúyela.
// Nota: Esto destruirá la sesión, no solo los datos de la sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destruir la sesión
session_destroy();

// 4. Redirigir al usuario a la página de login
header("Location: login.php");
exit();
?>