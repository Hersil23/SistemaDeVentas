<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');
$finMes = date('Y-m-t');

// Obtener configuración (tasa de cambio y moneda)
$config = [];
$resConfig = $conn->query("SELECT clave, valor FROM configuracion");
while ($row = $resConfig->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
$tasaCambio = floatval($config['tasa_cambio'] ?? 50);
$moneda = strtoupper($config['moneda_principal'] ?? 'USD');
$simboloMoneda = ($moneda === 'BS' || $moneda === 'VES') ? 'Bs' : '$';

// Obtener servicios con precios disponibles (de cuentas activas)
$serviciosPrecios = $conn->query("
    SELECT s.nombre as servicio, 
           MIN(c.precio_venta) as precio_min,
           MAX(c.precio_venta) as precio_max,
           COUNT(DISTINCT p.id) as perfiles_disponibles
    FROM servicios s
    INNER JOIN cuentas c ON c.servicio_id = s.id AND c.estado = 'activa'
    INNER JOIN perfiles p ON p.cuenta_id = c.id AND p.estado = 'disponible'
    WHERE c.precio_venta > 0
    GROUP BY s.id, s.nombre
    ORDER BY s.nombre
")->fetch_all(MYSQLI_ASSOC);

// Verificar si existe tabla pagos, si no usar ventas
$tablaPagosExiste = $conn->query("SHOW TABLES LIKE 'pagos'")->num_rows > 0;

if ($tablaPagosExiste) {
    // Ingresos desde pagos
    $stmtHoy = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE DATE(fecha_pago) = ?");
    $stmtHoy->bind_param("s", $hoy);
    $stmtHoy->execute();
    $ingresoHoy = $stmtHoy->get_result()->fetch_assoc()['total'];
    
    $stmtMes = $conn->prepare("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE fecha_pago BETWEEN ? AND ?");
    $stmtMes->bind_param("ss", $inicioMes, $finMes);
    $stmtMes->execute();
    $ingresoMes = $stmtMes->get_result()->fetch_assoc()['total'];
    
    $ingresoTotal = $conn->query("SELECT COALESCE(SUM(monto), 0) as total FROM pagos")->fetch_assoc()['total'];
    
    // Costos (solo ventas iniciales tienen costo)
    $costoTotal = $conn->query("SELECT COALESCE(SUM(v.precio_compra), 0) as total FROM pagos p INNER JOIN ventas v ON p.venta_id = v.id WHERE p.tipo = 'inicial'")->fetch_assoc()['total'];
    
    // Desglose del mes
    $pagosIniciales = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(monto), 0) as monto FROM pagos WHERE tipo = 'inicial' AND fecha_pago BETWEEN ? AND ?");
    $pagosIniciales->bind_param("ss", $inicioMes, $finMes);
    $pagosIniciales->execute();
    $datosIniciales = $pagosIniciales->get_result()->fetch_assoc();
    
    $pagosRenovaciones = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(monto), 0) as monto FROM pagos WHERE tipo = 'renovacion' AND fecha_pago BETWEEN ? AND ?");
    $pagosRenovaciones->bind_param("ss", $inicioMes, $finMes);
    $pagosRenovaciones->execute();
    $datosRenovaciones = $pagosRenovaciones->get_result()->fetch_assoc();
    
    // Últimos pagos
    $ultimosPagos = $conn->query("
        SELECT p.*, cl.nombre as cliente, s.nombre as servicio, pf.numero_perfil
        FROM pagos p
        INNER JOIN ventas v ON p.venta_id = v.id
        INNER JOIN clientes cl ON v.cliente_id = cl.id
        INNER JOIN perfiles pf ON v.perfil_id = pf.id
        INNER JOIN cuentas c ON pf.cuenta_id = c.id
        INNER JOIN servicios s ON c.servicio_id = s.id
        ORDER BY p.created_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    // Fallback: usar tabla ventas
    $stmtHoy = $conn->prepare("SELECT COALESCE(SUM(precio_venta), 0) as total FROM ventas WHERE DATE(fecha_venta) = ?");
    $stmtHoy->bind_param("s", $hoy);
    $stmtHoy->execute();
    $ingresoHoy = $stmtHoy->get_result()->fetch_assoc()['total'];
    
    $stmtMes = $conn->prepare("SELECT COALESCE(SUM(precio_venta), 0) as total FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmtMes->bind_param("ss", $inicioMes, $finMes);
    $stmtMes->execute();
    $ingresoMes = $stmtMes->get_result()->fetch_assoc()['total'];
    
    $ingresoTotal = $conn->query("SELECT COALESCE(SUM(precio_venta), 0) as total FROM ventas")->fetch_assoc()['total'];
    $costoTotal = $conn->query("SELECT COALESCE(SUM(precio_compra), 0) as total FROM ventas")->fetch_assoc()['total'];
    
    $datosIniciales = ['total' => 0, 'monto' => 0];
    $datosRenovaciones = ['total' => 0, 'monto' => 0];
    $ultimosPagos = [];
}

$utilidadHoy = $ingresoHoy;
$utilidadMes = $ingresoMes;
$utilidadTotal = $ingresoTotal - $costoTotal;

$inversionActiva = $conn->query("SELECT COALESCE(SUM(costo_compra), 0) as total FROM cuentas WHERE estado = 'activa'")->fetch_assoc()['total'];
$totalClientes = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 'activo'")->fetch_assoc()['total'];
$perfilesDisponibles = $conn->query("SELECT COUNT(*) as total FROM perfiles WHERE estado = 'disponible'")->fetch_assoc()['total'];
$perfilesVendidos = $conn->query("SELECT COUNT(*) as total FROM perfiles WHERE estado = 'vendido'")->fetch_assoc()['total'];

$fechaLimite = date('Y-m-d', strtotime('+7 days'));
$cuentasPorVencer = $conn->query("
    SELECT c.*, s.nombre as servicio,
           (SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND estado = 'vendido') as perfiles_vendidos
    FROM cuentas c INNER JOIN servicios s ON c.servicio_id = s.id
    WHERE c.estado = 'activa' AND c.fecha_vencimiento BETWEEN '$hoy' AND '$fechaLimite'
    ORDER BY c.fecha_vencimiento ASC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
require_once '../includes/header.php';
?>

<div class="min-h-screen flex bg-light-bg dark:bg-dark-bg">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-light-card dark:bg-dark-card border-r border-light-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="h-16 flex items-center gap-2 px-4 border-b border-light-border dark:border-dark-border">
            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
            <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 180px);">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Dashboard</a>
            <a href="vendedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>Vendedores</a>
            <a href="servicios.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>Servicios</a>
            <a href="proveedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Proveedores</a>
            <a href="cuentas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Cuentas</a>
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Clientes</a>
            <a href="ventas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>Ventas</a>
            <a href="reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
            <a href="configuracion.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Configuracion</a>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-light-border dark:border-dark-border bg-light-card dark:bg-dark-card">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold"><?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?></div>
                <div class="flex-1 min-w-0"><p class="text-sm font-medium text-slate-800 dark:text-white truncate"><?php echo e(getCurrentUserName()); ?></p><p class="text-xs text-slate-500">Administrador</p></div>
            </div>
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesion</a>
        </div>
    </aside>
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>
    
    <div class="flex-1 lg:ml-64">
        <header class="h-16 bg-light-card dark:bg-dark-card border-b border-light-border dark:border-dark-border flex items-center justify-between px-4 lg:px-6">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Dashboard</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="abrirModalPrecios()" class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <span class="hidden sm:inline">Lista de Precios</span>
                </button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Ingreso Hoy</p>
                    <p class="text-2xl font-bold text-green-600">$<?php echo number_format($ingresoHoy, 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Ingreso Mes</p>
                    <p class="text-2xl font-bold text-blue-600">$<?php echo number_format($ingresoMes, 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Utilidad Total</p>
                    <p class="text-2xl font-bold text-purple-600">$<?php echo number_format($utilidadTotal, 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Inversion Activa</p>
                    <p class="text-2xl font-bold text-amber-600">$<?php echo number_format($inversionActiva, 2); ?></p>
                </div>
            </div>
            
            <?php if ($tablaPagosExiste): ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Ventas Nuevas (Mes)</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white"><?php echo $datosIniciales['total']; ?></p>
                    <p class="text-xs text-green-600">$<?php echo number_format($datosIniciales['monto'], 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Renovaciones (Mes)</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white"><?php echo $datosRenovaciones['total']; ?></p>
                    <p class="text-xs text-teal-600">$<?php echo number_format($datosRenovaciones['monto'], 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Clientes Activos</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white"><?php echo $totalClientes; ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-sm text-slate-500 mb-1">Perfiles</p>
                    <p class="text-xl font-bold"><span class="text-green-600"><?php echo $perfilesDisponibles; ?></span> / <span class="text-red-600"><?php echo $perfilesVendidos; ?></span></p>
                    <p class="text-xs text-slate-400">Disp. / Vend.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Cuentas por Vencer (7 dias)</h2></div>
                    <?php if (empty($cuentasPorVencer)): ?>
                    <div class="p-4 text-center text-slate-500">No hay cuentas por vencer</div>
                    <?php else: ?>
                    <div class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($cuentasPorVencer as $cuenta): $dias = (strtotime($cuenta['fecha_vencimiento']) - time()) / 86400; ?>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-800 dark:text-white"><?php echo e($cuenta['servicio']); ?></p>
                                <p class="text-sm text-slate-500"><?php echo e($cuenta['cuenta']); ?></p>
                                <p class="text-xs text-slate-400"><?php echo $cuenta['perfiles_vendidos']; ?> vendidos</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold <?php echo $dias <= 3 ? 'text-red-600' : 'text-amber-600'; ?>"><?php echo $dias <= 0 ? 'Vencida' : ceil($dias).' dias'; ?></p>
                                <p class="text-xs text-slate-400"><?php echo date('d/m/Y', strtotime($cuenta['fecha_vencimiento'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($tablaPagosExiste && !empty($ultimosPagos)): ?>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Ultimos Pagos</h2></div>
                    <div class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($ultimosPagos as $pago): ?>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-slate-800 dark:text-white"><?php echo e($pago['cliente']); ?></p>
                                <p class="text-sm text-slate-500"><?php echo e($pago['servicio']); ?></p>
                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full <?php echo $pago['tipo'] === 'inicial' ? 'bg-green-100 text-green-600' : 'bg-teal-100 text-teal-600'; ?>"><?php echo $pago['tipo'] === 'inicial' ? 'Nueva' : 'Renovacion'; ?></span>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600">$<?php echo number_format($pago['monto'], 2); ?></p>
                                <p class="text-xs text-slate-400"><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal Lista de Precios -->
<div id="modalPrecios" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModalPrecios()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
            <h3 class="font-semibold text-slate-800 dark:text-white">Lista de Precios</h3>
            <button onclick="cerrarModalPrecios()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        
        <div class="p-4 overflow-y-auto flex-1">
            <?php if (empty($serviciosPrecios)): ?>
            <p class="text-center text-slate-500 py-8">No hay servicios disponibles</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($serviciosPrecios as $srv): 
                    $precioUSD = $srv['precio_min'];
                    // Si moneda es Bs, multiplicar por tasa
                    $precioMostrar = ($moneda === 'BS' || $moneda === 'VES') ? ($precioUSD * $tasaCambio) : $precioUSD;
                ?>
                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-slate-800 dark:text-white"><?php echo e($srv['servicio']); ?></p>
                            <p class="text-xs text-slate-400"><?php echo $srv['perfiles_disponibles']; ?> disponibles</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-green-600"><?php echo $simboloMoneda; ?> <?php echo number_format($precioMostrar, 2); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t border-light-border dark:border-dark-border">
            <button onclick="copiarPrecios()" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Copiar para WhatsApp
            </button>
            <p id="mensajeCopia" class="text-center text-green-600 text-sm mt-2 hidden">¡Copiado al portapapeles!</p>
        </div>
    </div>
</div>

<script>
// Datos de precios desde PHP
const serviciosPrecios = <?php echo json_encode($serviciosPrecios); ?>;
const tasaCambio = <?php echo $tasaCambio; ?>;
const moneda = '<?php echo $moneda; ?>';
const simboloMoneda = '<?php echo $simboloMoneda; ?>';
const esBolivares = (moneda === 'BS' || moneda === 'VES');

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}

function abrirModalPrecios() {
    document.getElementById('modalPrecios').classList.remove('hidden');
}

function cerrarModalPrecios() {
    document.getElementById('modalPrecios').classList.add('hidden');
    document.getElementById('mensajeCopia').classList.add('hidden');
}

function copiarPrecios() {
    if (serviciosPrecios.length === 0) {
        alert('No hay servicios disponibles');
        return;
    }
    
    let texto = '📋 *LISTA DE PRECIOS*\n';
    texto += '━━━━━━━━━━━━━━━━\n\n';
    
    serviciosPrecios.forEach(srv => {
        const precioUSD = parseFloat(srv.precio_min);
        const precio = esBolivares ? (precioUSD * tasaCambio) : precioUSD;
        texto += '🎬 *' + srv.servicio + '*\n';
        texto += '   💵 ' + simboloMoneda + ' ' + precio.toFixed(2) + '\n\n';
    });
    
    texto += '━━━━━━━━━━━━━━━━\n';
    texto += '✅ Perfil asignado por usuario\n';
    texto += '✅ Garantia incluida\n';
    
    navigator.clipboard.writeText(texto).then(() => {
        document.getElementById('mensajeCopia').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('mensajeCopia').classList.add('hidden');
        }, 3000);
    }).catch(err => {
        const textarea = document.createElement('textarea');
        textarea.value = texto;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        document.getElementById('mensajeCopia').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('mensajeCopia').classList.add('hidden');
        }, 3000);
    });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModalPrecios();
});
</script>

<?php require_once '../includes/footer.php'; ?>