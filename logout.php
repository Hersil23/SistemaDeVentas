<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Registrar actividad antes de destruir sesión
if (isLoggedIn()) {
    logActivity('logout', 'Cierre de sesion');
}

// Destruir sesión
destroySession();

// Redirigir al login
header('Location: index.php');
exit;