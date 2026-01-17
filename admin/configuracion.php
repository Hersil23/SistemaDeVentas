<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$mensaje = '';
$error = '';

// Configuraciones por defecto
$defaults = [
    'nombre_negocio' => 'Mi Negocio',
    'moneda_principal' => 'USD',
    'tasa_cambio' => '50',
    'banco' => '',
    'telefono_pago' => '',
    'cuenta_banco' => '',
    'mensaje_entrega' => 'Hola {cliente}!
Aqui estan los datos de tu cuenta de *{servicio}*:

*Correo:* {cuenta}
*Contrasena:* {password}
*Perfil:* {perfil}
*PIN:* {pin}

*Adquirido:* {fecha_compra}
*Vence:* {vencimiento}

Gracias por tu compra!',
    'mensaje_cambio' => 'Hola {cliente}!
Te informamos que hubo un cambio en tu cuenta de *{servicio}*:

*Nuevo Correo:* {cuenta}
*Nueva Contrasena:* {password}
*Perfil:* {perfil}
*PIN:* {pin}

Disculpa las molestias. Cualquier duda estamos a la orden!',
    'mensaje_cobro' => 'IMPORTANTE! NO ATENDEMOS EMERGENCIAS

Estimad(a) *{cliente}*
Le informamos que su servicio de *{servicio}* ha vencido.
Fecha de renovacion: *{vencimiento}*

Para renovar, realice el pago de *{precio}* a traves de:
*{banco}*
- Tlf: {telefono_pago}
- Cuenta: {cuenta_banco}

Gracias! Si no desea renovar, por favor informenos.

Nota importante:
- Si el pago se realiza fuera de la fecha indicada, su perfil o cuenta sera activado al dia siguiente.
- Atendemos a mas de 400 personas, por lo que le pedimos tomar previsiones.
- Al ignorar este mensaje asumimos que no desea renovar y se procedera a suspender el servicio.',
    'mensaje_renovar' => 'Hola *{cliente}*!
Su pago ha sido confirmado.

*Servicio:* {servicio}
*Nueva fecha de vencimiento:* {vencimiento}

Gracias por renovar con nosotros!'
];

// Obtener configuraciones actuales
$configResult = $conn->query("SELECT clave, valor FROM configuracion");
$config = [];
while ($row = $configResult->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Merge con defaults
foreach ($defaults as $key => $value) {
    if (!isset($config[$key])) {
        $config[$key] = $value;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina.';
    } else {
        $campos = [
            'nombre_negocio',
            'moneda_principal',
            'tasa_cambio',
            'banco',
            'telefono_pago',
            'cuenta_banco',
            'mensaje_entrega',
            'mensaje_cambio',
            'mensaje_cobro',
            'mensaje_renovar'
        ];
        
        $conn->begin_transaction();
        try {
            foreach ($campos as $campo) {
                $valor = $_POST[$campo] ?? '';
                
                // Verificar si existe
                $stmt = $conn->prepare("SELECT id FROM configuracion WHERE clave = ?");
                $stmt->bind_param("s", $campo);
                $stmt->execute();
                $existe = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                
                if ($existe) {
                    $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
                    $stmt->bind_param("ss", $valor, $campo);
                } else {
                    $stmt = $conn->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?)");
                    $stmt->bind_param("ss", $campo, $valor);
                }
                $stmt->execute();
                $stmt->close();
                
                $config[$campo] = $valor;
            }
            
            $conn->commit();
            $mensaje = 'Configuracion guardada correctamente';
            logActivity('configuracion_actualizada', 'Configuracion general');
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error al guardar la configuracion';
        }
    }
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
                
                <!-- Configuración General -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border">
                        <h2 class="font-semibold text-slate-800 dark:text-white">General</h2>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre del Negocio</label>
                            <input type="text" name="nombre_negocio" value="<?php echo e($config['nombre_negocio']); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Moneda Principal</label>
                            <select name="moneda_principal" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                                <option value="USD" <?php echo $config['moneda_principal'] === 'USD' ? 'selected' : ''; ?>>Dolares (USD)</option>
                                <option value="BS" <?php echo $config['moneda_principal'] === 'BS' ? 'selected' : ''; ?>>Bolivares (Bs)</option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Afecta la variable {precio} en mensajes</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tasa de Cambio (1 USD = X Bs)</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.01" name="tasa_cambio" id="tasa_cambio" value="<?php echo e($config['tasa_cambio']); ?>" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="50.00">
                            </div>
                            <div class="flex gap-2 mt-2">
                                <button type="button" onclick="obtenerTasaParalelo()" class="flex-1 px-3 py-1.5 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50">
                                    <span id="btnParalelo">Paralelo</span>
                                </button>
                                <button type="button" onclick="obtenerTasaBCV()" class="flex-1 px-3 py-1.5 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/50">
                                    <span id="btnBCV">BCV</span>
                                </button>
                                <button type="button" onclick="obtenerTasaBinance()" class="flex-1 px-3 py-1.5 text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-lg hover:bg-amber-200 dark:hover:bg-amber-900/50">
                                    <span id="btnBinance">Binance</span>
                                </button>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Click en un boton para actualizar automaticamente</p>
                        </div>
                    </div>
                </div>
                
                <!-- Datos Bancarios -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border">
                        <h2 class="font-semibold text-slate-800 dark:text-white">Datos de Pago</h2>
                        <p class="text-sm text-slate-500">Se usan en el mensaje de cobro</p>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Banco</label>
                            <input type="text" name="banco" value="<?php echo e($config['banco']); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="Banco de Venezuela">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Telefono Pago Movil</label>
                            <input type="text" name="telefono_pago" value="<?php echo e($config['telefono_pago']); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="04145116337">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Numero de Cuenta</label>
                            <input type="text" name="cuenta_banco" value="<?php echo e($config['cuenta_banco']); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="13313482 (0102)">
                        </div>
                    </div>
                </div>
                
                <!-- Mensajes WhatsApp -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border">
                        <h2 class="font-semibold text-slate-800 dark:text-white">Mensajes de WhatsApp</h2>
                        <p class="text-sm text-slate-500">Variables: {cliente}, {servicio}, {cuenta}, {password}, {perfil}, {pin}, {fecha_compra}, {vencimiento}, {precio}, {precio_usd}, {precio_bs}, {banco}, {telefono_pago}, {cuenta_banco}</p>
                    </div>
                    <div class="p-4 space-y-4">
                        <!-- Mensaje Entrega -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Mensaje de Entrega</label>
                            </div>
                            <textarea name="mensaje_entrega" rows="8" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-mono text-sm"><?php echo e($config['mensaje_entrega']); ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Se envia al vender un perfil</p>
                        </div>
                        
                        <!-- Mensaje Cambio -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Mensaje de Cambio</label>
                            </div>
                            <textarea name="mensaje_cambio" rows="8" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-mono text-sm"><?php echo e($config['mensaje_cambio']); ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Se envia cuando cambia la contrasena o datos</p>
                        </div>
                        
                        <!-- Mensaje Cobro -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Mensaje de Cobro</label>
                            </div>
                            <textarea name="mensaje_cobro" rows="12" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-mono text-sm"><?php echo e($config['mensaje_cobro']); ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Se envia como recordatorio de pago</p>
                        </div>
                        
                        <!-- Mensaje Renovar -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-purple-500"></span>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Mensaje de Renovacion</label>
                            </div>
                            <textarea name="mensaje_renovar" rows="6" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white font-mono text-sm"><?php echo e($config['mensaje_renovar']); ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Se envia al confirmar el pago y renovar</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-primary-500 hover:bg-primary-600 text-white font-medium rounded-lg">Guardar Configuracion</button>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}

// Obtener tasa del Dólar Paralelo
async function obtenerTasaParalelo() {
    const btn = document.getElementById('btnParalelo');
    const original = btn.textContent;
    btn.textContent = 'Cargando...';
    
    try {
        const response = await fetch('ajax/obtener_tasa.php?fuente=paralelo');
        const data = await response.json();
        
        if (data.success && data.tasa) {
            document.getElementById('tasa_cambio').value = data.tasa;
            btn.textContent = 'Bs ' + data.tasa;
            setTimeout(() => btn.textContent = original, 3000);
        } else {
            btn.textContent = 'Error';
            setTimeout(() => btn.textContent = original, 2000);
            alert(data.error || 'No se pudo obtener la tasa');
        }
    } catch (error) {
        btn.textContent = 'Error';
        setTimeout(() => btn.textContent = original, 2000);
        alert('Error de conexion');
    }
}

// Obtener tasa del BCV
async function obtenerTasaBCV() {
    const btn = document.getElementById('btnBCV');
    const original = btn.textContent;
    btn.textContent = 'Cargando...';
    
    try {
        const response = await fetch('ajax/obtener_tasa.php?fuente=bcv');
        const data = await response.json();
        
        if (data.success && data.tasa) {
            document.getElementById('tasa_cambio').value = data.tasa;
            btn.textContent = 'Bs ' + data.tasa;
            setTimeout(() => btn.textContent = original, 3000);
        } else {
            btn.textContent = 'Error';
            setTimeout(() => btn.textContent = original, 2000);
            alert(data.error || 'No se pudo obtener la tasa');
        }
    } catch (error) {
        btn.textContent = 'Error';
        setTimeout(() => btn.textContent = original, 2000);
        alert('Error de conexion');
    }
}

// Obtener tasa de Binance P2P
async function obtenerTasaBinance() {
    const btn = document.getElementById('btnBinance');
    const original = btn.textContent;
    btn.textContent = 'Cargando...';
    
    try {
        const response = await fetch('ajax/obtener_tasa.php?fuente=binance');
        const data = await response.json();
        
        if (data.success && data.tasa) {
            document.getElementById('tasa_cambio').value = data.tasa;
            btn.textContent = 'Bs ' + data.tasa;
            setTimeout(() => btn.textContent = original, 3000);
        } else {
            btn.textContent = 'Error';
            setTimeout(() => btn.textContent = original, 2000);
            alert(data.error || 'No se pudo obtener la tasa');
        }
    } catch (error) {
        btn.textContent = 'Error';
        setTimeout(() => btn.textContent = original, 2000);
        alert('Error de conexion');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>