<?php
// =============================================
// SISTEMA DE VENTAS - AUTENTICACIÓN Y SEGURIDAD
// =============================================

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de sesión
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    
    session_start();
}

// =============================================
// CONFIGURACIÓN
// =============================================
define('SESSION_TIMEOUT', 3600); // 1 hora de inactividad
define('MAX_LOGIN_ATTEMPTS', 5); // Máximo intentos de login
define('LOCKOUT_TIME', 900); // 15 minutos de bloqueo

// =============================================
// FUNCIONES DE VERIFICACIÓN DE SESIÓN
// =============================================

/**
 * Verifica si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

/**
 * Verifica si el usuario es admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['rol'] === 'admin';
}

/**
 * Verifica si el usuario es vendedor
 */
function isVendedor() {
    return isLoggedIn() && $_SESSION['rol'] === 'vendedor';
}

/**
 * Obtiene el ID del usuario actual
 */
function getCurrentUserId() {
    return $_SESSION['usuario_id'] ?? null;
}

/**
 * Obtiene el rol del usuario actual
 */
function getCurrentUserRole() {
    return $_SESSION['rol'] ?? null;
}

/**
 * Obtiene el nombre completo del usuario actual
 */
function getCurrentUserName() {
    if (isLoggedIn()) {
        return $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
    }
    return '';
}

// =============================================
// FUNCIONES DE PROTECCIÓN
// =============================================

/**
 * Verifica timeout de sesión
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            // Sesión expirada
            destroySession();
            header('Location: /SistemaDeVentas/index.php?error=session_expired');
            exit;
        }
    }
    // Actualizar tiempo de actividad
    $_SESSION['last_activity'] = time();
}

/**
 * Regenera el ID de sesión para prevenir session fixation
 */
function regenerateSession() {
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerar cada 30 minutos
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Destruye la sesión completamente
 */
function destroySession() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// =============================================
// FUNCIONES CSRF
// =============================================

/**
 * Genera un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica el token CSRF
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Genera el campo hidden con el token CSRF
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Valida CSRF en peticiones POST
 */
function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('Error de seguridad: Token CSRF invalido');
        }
    }
}

// =============================================
// PROTECCIÓN CONTRA FUERZA BRUTA
// =============================================

/**
 * Registra un intento de login fallido
 */
function registerFailedAttempt($ip) {
    global $conn;
    
    // Crear tabla si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Verifica si la IP está bloqueada
 */
function isIPBlocked($ip) {
    global $conn;
    
    // Verificar si la tabla existe
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result->num_rows === 0) {
        return false;
    }
    
    $lockoutTime = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
    $stmt->bind_param("ss", $ip, $lockoutTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Limpia intentos antiguos de login
 */
function cleanOldAttempts() {
    global $conn;
    
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result->num_rows > 0) {
        $oldTime = date('Y-m-d H:i:s', time() - LOCKOUT_TIME);
        $conn->query("DELETE FROM login_attempts WHERE attempt_time < '$oldTime'");
    }
}

/**
 * Limpia intentos después de login exitoso
 */
function clearLoginAttempts($ip) {
    global $conn;
    
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Obtiene la IP real del cliente
 */
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// =============================================
// FUNCIONES DE ACCESO A PÁGINAS
// =============================================

/**
 * Requiere que el usuario esté logueado
 */
function requireLogin() {
    checkSessionTimeout();
    regenerateSession();
    
    if (!isLoggedIn()) {
        header('Location: /SistemaDeVentas/index.php');
        exit;
    }
}

/**
 * Requiere que el usuario sea admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('Location: /SistemaDeVentas/index.php?error=unauthorized');
        exit;
    }
}

function requireVendedor() {
    requireLogin();
    
    // Admin también puede acceder al panel vendedor
    if (!isVendedor() && !isAdmin()) {
        header('Location: /SistemaDeVentas/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Requiere que el usuario sea admin o vendedor
 */
function requireAdminOrVendedor() {
    requireLogin();
    
    if (!isAdmin() && !isVendedor()) {
        header('Location: /SistemaDeVentas/index.php?error=unauthorized');
        exit;
    }
}

// =============================================
// FUNCIONES DE SANITIZACIÓN
// =============================================

/**
 * Escapa salida HTML para prevenir XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitiza entrada de texto
 */
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    return $input;
}

/**
 * Sanitiza entrada numérica
 */
function sanitizeInt($input) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitiza entrada decimal
 */
function sanitizeDecimal($input) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

/**
 * Valida email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// =============================================
// FUNCIONES DE LOGGING
// =============================================

/**
 * Registra actividad del usuario
 */
function logActivity($action, $description = '') {
    global $conn;
    
    // Crear tabla si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NULL,
        ip_address VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $userId = getCurrentUserId();
    $ip = getClientIP();
    
    $stmt = $conn->prepare("INSERT INTO activity_log (usuario_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}

// =============================================
// HEADERS DE SEGURIDAD
// =============================================

/**
 * Establece headers de seguridad
 */
function setSecurityHeaders() {
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevenir MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar XSS protection del navegador
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Aplicar headers de seguridad automáticamente
setSecurityHeaders();