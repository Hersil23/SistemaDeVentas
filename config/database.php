<?php
// =============================================
// SISTEMA DE VENTAS - CONEXIÓN A BASE DE DATOS
// =============================================

// Detectar si es local o servidor
$isLocal = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1');

if ($isLocal) {
    // LOCAL (XAMPP)
    $host = 'localhost:3307';
    $user = 'root';
    $pass = '';
    $db   = 'sistema_ventas';
} else {
    // SERVIDOR (cPanel) - Cambiar estos datos cuando subas al servidor
    $host = 'localhost';
    $user = 'TU_USUARIO_CPANEL';
    $pass = 'TU_PASSWORD_CPANEL';
    $db   = 'TU_BASEDATOS_CPANEL';
}

// Crear conexión
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");

// Zona horaria (ajustar según tu país)
date_default_timezone_set('America/Caracas');