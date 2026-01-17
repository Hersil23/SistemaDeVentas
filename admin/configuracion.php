<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

// Actualizar tasa manualmente
if (isset($_GET['actualizar_tasa'])) {
    require_once '../includes/moneda.php';
    $result = $conn->query("SELECT valor FROM configuracion WHERE clave = 'moneda_fuente'");
    $fuente = 'binance';
    if ($result && $result->num_rows > 0) {
        $fuente = $result->fetch_assoc()['valor'];
    }
    actualizarTasa($fuente);
    header('Location: configuracion.php?msg=tasa_actualizada');
    exit;
}

$mensaje = '';
$error = '';

$conn->query("CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$defaults = [
    'nombre_negocio' => ['Sistema de Ventas', 'Nombre de tu negocio'],
    'telefono_whatsapp' => ['', 'Numero WhatsApp para soporte'],
    'email_contacto' => ['', 'Email de contacto'],
    'moneda_simbolo' => ['$', 'Simbolo de moneda'],
    'moneda_nombre' => ['USD', 'Nombre de moneda'],
    'mensaje_whatsapp' => ["Hola {cliente}!\n\nDatos de tu cuenta de *{servicio}*:\n\nCorreo: {cuenta}\nContrasena: {password}\nPerfil: {perfil}\n\nVence: {vencimiento}\n\nGracias!", 'Plantilla WhatsApp'],
    'dias_alerta_vencimiento' => ['7', 'Dias antes de vencimiento'],
    'moneda_fuente' => ['binance', 'Fuente de tasa'],
    'mostrar_bs' => ['1', 'Mostrar Bolivares'],
];

foreach ($defaults as $clave => $data) {
    $stmt = $conn->prepare("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $clave, $data[0], $data[1]);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina.';
    } else {
        $campos = ['nombre_negocio', 'telefono_whatsapp', 'email_contacto', 'moneda_simbolo', 'moneda_nombre', 'mensaje_whatsapp', 'dias_alerta_vencimiento', 'moneda_fuente', 'mostrar_bs'];
        foreach ($campos as $campo) {
            if (isset($_POST[$campo])) {
                $valor = sanitizeInput($_POST[$campo]);
                $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
                $stmt->bind_param("ss", $valor, $campo);
                $stmt->execute();
                $stmt->close();
            }
        }
        $mensaje = 'Configuracion guardada correctamente';
        logActivity('configuracion_actualizada', 'Configuracion del sistema actualizada');
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'tasa_actualizada') {
    $mensaje = 'Tasa de cambio actualizada correctamente';
}

$config = [];
$result = $conn->query("SELECT clave, valor, descripcion FROM configuracion");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = ['valor' => $row['valor'], 'descripcion' => $row['descripcion']];
}

$pageTitle = 'Configuracion';
require_once '../includes/header.php';
?>

<div class="min-h-screen flex bg-light-bg dark:bg-dark-bg">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-light-card dark:bg-dark-card border-r border-light-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="h-16 flex items-center gap-2 px-4 border-b border-light-border dark:border-dark-border">
            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
            <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Dashboard</a>
            <a href="vendedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>Vendedores</a>
            <a href="servicios.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>Servicios</a>
            <a href="proveedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Proveedores</a>
            <a href="cuentas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Cuentas</a>
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Clientes</a>
            <a href="ventas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>Ventas</a>
            <a href="reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
            <a href="configuracion.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Configuracion</a>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-light-border dark:border-dark-border">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold"><?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?></div>
                <div class="flex-1 min-w-0"><p class="text-sm font-medium text-slate-800 dark:text-white truncate"><?php echo e(getCurrentUserName()); ?></p><p class="text-xs text-slate-500 dark:text-slate-400">Administrador</p></div>
            </div>
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesion</a>
        </div>
    </aside>
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>
    
    <div class="flex-1 lg:ml-64">
        <header class="h-16 bg-light-card dark:bg-dark-card border-b border-light-border dark:border-dark-border flex items-center justify-between px-4 lg:px-6">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Configuracion</h1>
            </div>
            <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <?php echo csrfField(); ?>
                
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Informacion del Negocio</h2></div>
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre del negocio</label>
                            <input type="text" name="nombre_negocio" value="<?php echo e($config['nombre_negocio']['valor'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">WhatsApp Soporte</label>
                                <input type="text" name="telefono_whatsapp" value="<?php echo e($config['telefono_whatsapp']['valor'] ?? ''); ?>" placeholder="+584141234567" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email contacto</label>
                                <input type="email" name="email_contacto" value="<?php echo e($config['email_contacto']['valor'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Moneda Principal</h2></div>
                    <div class="p-4 grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Simbolo</label>
                            <input type="text" name="moneda_simbolo" value="<?php echo e($config['moneda_simbolo']['valor'] ?? '$'); ?>" maxlength="5" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre</label>
                            <input type="text" name="moneda_nombre" value="<?php echo e($config['moneda_nombre']['valor'] ?? 'USD'); ?>" maxlength="10" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white flex items-center gap-2"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>Tasa de Cambio (USD/Bs)</h2></div>
                    <div class="p-4 space-y-4">
                        <?php 
                        require_once '../includes/moneda.php';
                        $infoTasa = obtenerInfoTasa();
                        $todasTasas = obtenerTodasLasTasas();
                        ?>
                        
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div>
                                    <p class="text-sm text-blue-600 dark:text-blue-400">Tasa actual (<?php echo ucfirst($infoTasa['fuente']); ?>)</p>
                                    <p class="text-2xl font-bold text-slate-800 dark:text-white">Bs. <?php echo number_format($infoTasa['tasa'], 2, ',', '.'); ?></p>
                                    <?php if ($infoTasa['actualizado']): ?><p class="text-xs text-slate-500 mt-1">Actualizado: <?php echo date('d/m/Y H:i', strtotime($infoTasa['actualizado'])); ?></p><?php endif; ?>
                                </div>
                                <a href="?actualizar_tasa=1" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-lg">Actualizar</a>
                            </div>
                        </div>
                        
                        <?php if (!empty($todasTasas)): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <?php foreach ($todasTasas as $t): ?>
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg text-center">
                                <p class="text-xs text-slate-500 mb-1"><?php echo e($t['nombre']); ?></p>
                                <p class="font-bold text-slate-800 dark:text-white"><?php echo number_format($t['tasa'], 2, ',', '.'); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fuente de tasa</label>
                                <select name="moneda_fuente" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                    <option value="bcv" <?php echo ($config['moneda_fuente']['valor'] ?? '') === 'bcv' ? 'selected' : ''; ?>>BCV (Oficial)</option>
                                    <option value="paralelo" <?php echo ($config['moneda_fuente']['valor'] ?? '') === 'paralelo' ? 'selected' : ''; ?>>Paralelo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Mostrar en Bolivares</label>
                                <select name="mostrar_bs" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                    <option value="1" <?php echo ($config['mostrar_bs']['valor'] ?? '1') === '1' ? 'selected' : ''; ?>>Si</option>
                                    <option value="0" <?php echo ($config['mostrar_bs']['valor'] ?? '1') === '0' ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Plantilla WhatsApp</h2></div>
                    <div class="p-4">
                        <textarea name="mensaje_whatsapp" rows="6" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-mono text-sm"><?php echo e($config['mensaje_whatsapp']['valor'] ?? ''); ?></textarea>
                        <p class="text-xs text-slate-500 mt-2">Variables: {cliente} {servicio} {cuenta} {password} {perfil} {vencimiento}</p>
                    </div>
                </div>
                
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Alertas</h2></div>
                    <div class="p-4">
                        <div class="max-w-xs">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Dias antes de vencimiento</label>
                            <input type="number" name="dias_alerta_vencimiento" value="<?php echo e($config['dias_alerta_vencimiento']['valor'] ?? '7'); ?>" min="1" max="30" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end"><button type="submit" class="px-6 py-3 bg-primary-500 hover:bg-primary-600 text-white font-medium rounded-lg">Guardar Configuracion</button></div>
            </form>
        </main>
    </div>
</div>

<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>

<?php require_once '../includes/footer.php'; ?>