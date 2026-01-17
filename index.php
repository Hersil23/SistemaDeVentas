<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: vendedor/dashboard.php');
    }
    exit;
}

require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';
$ip = getClientIP();

// Verificar si la IP está bloqueada
if (isIPBlocked($ip)) {
    $error = 'Demasiados intentos fallidos. Intente nuevamente en 15 minutos';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina e intente nuevamente';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Por favor complete todos los campos';
        } else if (!validateEmail($email)) {
            $error = 'Ingrese un correo electronico valido';
        } else {
            // Buscar usuario
            $stmt = $conn->prepare("SELECT id, nombre, apellido, email, password, rol, estado FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $usuario = $result->fetch_assoc();
                
                // Verificar contraseña
                if (password_verify($password, $usuario['password'])) {
                    // Verificar estado
                    if ($usuario['estado'] === 'activo') {
                        // Limpiar intentos fallidos
                        clearLoginAttempts($ip);
                        
                        // Regenerar ID de sesión
                        session_regenerate_id(true);
                        
                        // Crear sesión
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['nombre'] = $usuario['nombre'];
                        $_SESSION['apellido'] = $usuario['apellido'];
                        $_SESSION['email'] = $usuario['email'];
                        $_SESSION['rol'] = $usuario['rol'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['created'] = time();
                        
                        // Registrar actividad
                        logActivity('login', 'Inicio de sesion exitoso');
                        
                        // Redirigir según rol
                        if ($usuario['rol'] === 'admin') {
                            header('Location: admin/dashboard.php');
                        } else {
                            header('Location: vendedor/dashboard.php');
                        }
                        exit;
                    } else {
                        $error = 'Su cuenta esta inactiva. Contacte al administrador';
                        logActivity('login_blocked', 'Cuenta inactiva: ' . $email);
                    }
                } else {
                    registerFailedAttempt($ip);
                    logActivity('login_failed', 'Contrasena incorrecta: ' . $email);
                    $error = 'Credenciales incorrectas';
                }
            } else {
                registerFailedAttempt($ip);
                logActivity('login_failed', 'Usuario no encontrado: ' . $email);
                $error = 'Credenciales incorrectas';
            }
            $stmt->close();
        }
    }
}

// Limpiar intentos antiguos ocasionalmente
if (rand(1, 10) === 1) {
    cleanOldAttempts();
}

// Mensaje de sesión expirada
if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = 'Su sesion ha expirado. Inicie sesion nuevamente';
}

// Mensaje de acceso no autorizado
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'No tiene permisos para acceder a esa seccion';
}

$pageTitle = 'Iniciar Sesion';
require_once 'includes/header.php';
?>

<div class="min-h-screen flex flex-col">
    
    <nav class="bg-light-card dark:bg-dark-card border-b border-light-border dark:border-dark-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <a href="landing.php" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
                </a>
                
                <button onclick="toggleDarkMode()" 
                        class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
                        title="Cambiar tema">
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </div>
        </div>
    </nav>
    
    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            
            <div class="bg-light-card dark:bg-dark-card rounded-2xl shadow-xl border border-light-border dark:border-dark-border p-6 sm:p-8">
                
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Iniciar Sesion</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm">Ingresa tus credenciales para acceder</p>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-5">
                    
                    <?php echo csrfField(); ?>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Correo electronico
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo e($_POST['email'] ?? ''); ?>"
                               required
                               autocomplete="email"
                               class="w-full px-4 py-3 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                               placeholder="correo@ejemplo.com">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Contrasena
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required
                                   autocomplete="current-password"
                                   class="w-full px-4 py-3 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                                   placeholder="Ingresa tu contrasena">
                            <button type="button" 
                                    onclick="togglePassword()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full py-3 px-4 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-lg shadow-lg shadow-primary-500/30 hover:shadow-primary-500/40 transition-all duration-300">
                        Ingresar
                    </button>
                    
                </form>
                
                <div class="mt-6 text-center">
                    <a href="landing.php" class="text-sm text-slate-500 dark:text-slate-400 hover:text-primary-500 dark:hover:text-primary-400 transition-colors">
                        Volver al inicio
                    </a>
                </div>
                
            </div>
            
        </div>
    </main>
    
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        `;
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        `;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>