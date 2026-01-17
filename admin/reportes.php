<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$periodo = $_GET['periodo'] ?? 'mes';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

switch ($periodo) {
    case 'hoy': $fecha_inicio = $fecha_fin = date('Y-m-d'); break;
    case 'semana': $fecha_inicio = date('Y-m-d', strtotime('monday this week')); $fecha_fin = date('Y-m-d'); break;
    case 'mes': $fecha_inicio = date('Y-m-01'); $fecha_fin = date('Y-m-d'); break;
    case 'anio': $fecha_inicio = date('Y-01-01'); $fecha_fin = date('Y-m-d'); break;
    case 'personalizado':
        if (empty($fecha_inicio)) $fecha_inicio = date('Y-m-01');
        if (empty($fecha_fin)) $fecha_fin = date('Y-m-d');
        break;
    default: $fecha_inicio = date('Y-m-01'); $fecha_fin = date('Y-m-d');
}

// Resumen
$stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(precio_venta),0) as ingresos, COALESCE(AVG(precio_venta),0) as promedio, COUNT(CASE WHEN estado='activa' THEN 1 END) as activas FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$resumen = $stmt->get_result()->fetch_assoc();

// Por servicio
$stmt = $conn->prepare("SELECT s.nombre as servicio, COUNT(v.id) as cantidad, SUM(v.precio_venta) as total FROM ventas v INNER JOIN perfiles p ON v.perfil_id=p.id INNER JOIN cuentas c ON p.cuenta_id=c.id INNER JOIN servicios s ON c.servicio_id=s.id WHERE v.fecha_venta BETWEEN ? AND ? GROUP BY s.id ORDER BY total DESC");
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$porServicio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Por vendedor
$stmt = $conn->prepare("SELECT CONCAT(u.nombre,' ',u.apellido) as vendedor, COUNT(v.id) as cantidad, SUM(v.precio_venta) as total FROM ventas v INNER JOIN usuarios u ON v.vendedor_id=u.id WHERE v.fecha_venta BETWEEN ? AND ? GROUP BY u.id ORDER BY total DESC");
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$porVendedor = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Por día
$stmt = $conn->prepare("SELECT DATE(fecha_venta) as fecha, COUNT(*) as cantidad, SUM(precio_venta) as total FROM ventas WHERE fecha_venta BETWEEN ? AND ? GROUP BY DATE(fecha_venta) ORDER BY fecha DESC LIMIT 7");
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$porDia = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

// Top clientes
$stmt = $conn->prepare("SELECT CONCAT(cl.nombre,' ',cl.apellido) as cliente, COUNT(v.id) as cantidad, SUM(v.precio_venta) as total FROM ventas v INNER JOIN clientes cl ON v.cliente_id=cl.id WHERE v.fecha_venta BETWEEN ? AND ? GROUP BY cl.id ORDER BY total DESC LIMIT 5");
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$topClientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Exportar CSV
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Reporte de Ventas', $fecha_inicio, $fecha_fin]);
    fputcsv($out, ['Total Ventas', $resumen['total']]);
    fputcsv($out, ['Ingresos', '$' . number_format($resumen['ingresos'], 2)]);
    fputcsv($out, []);
    fputcsv($out, ['POR SERVICIO']);
    fputcsv($out, ['Servicio', 'Cantidad', 'Total']);
    foreach ($porServicio as $r) fputcsv($out, [$r['servicio'], $r['cantidad'], '$' . number_format($r['total'], 2)]);
    fputcsv($out, []);
    fputcsv($out, ['POR VENDEDOR']);
    fputcsv($out, ['Vendedor', 'Cantidad', 'Total']);
    foreach ($porVendedor as $r) fputcsv($out, [$r['vendedor'], $r['cantidad'], '$' . number_format($r['total'], 2)]);
    fclose($out);
    exit;
}

$pageTitle = 'Reportes';
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
            <a href="reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
            <a href="configuracion.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Configuracion</a>
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
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Reportes</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="?periodo=<?php echo $periodo; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&exportar=1" class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg><span class="hidden sm:inline">Exportar</span></a>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            
            <!-- Filtros -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4 mb-6">
                <form method="GET" class="flex flex-col lg:flex-row gap-3 items-end">
                    <div class="flex flex-wrap gap-2">
                        <a href="?periodo=hoy" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $periodo==='hoy'?'bg-primary-500 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'; ?>">Hoy</a>
                        <a href="?periodo=semana" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $periodo==='semana'?'bg-primary-500 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'; ?>">Semana</a>
                        <a href="?periodo=mes" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $periodo==='mes'?'bg-primary-500 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'; ?>">Mes</a>
                        <a href="?periodo=anio" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $periodo==='anio'?'bg-primary-500 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'; ?>">Año</a>
                    </div>
                    <div class="flex-1"></div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="hidden" name="periodo" value="personalizado">
                        <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" class="px-3 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white text-sm">
                        <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" class="px-3 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white text-sm">
                        <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg text-sm">Filtrar</button>
                    </div>
                </form>
                <p class="text-sm text-slate-500 mt-2"><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></p>
            </div>
            
            <!-- Resumen -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Ingresos</p>
                    <p class="text-2xl font-bold text-green-600">$<?php echo number_format($resumen['ingresos'], 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Total Ventas</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white"><?php echo $resumen['total']; ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Promedio</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white">$<?php echo number_format($resumen['promedio'], 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Activas</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $resumen['activas']; ?></p>
                </div>
            </div>
            
            <div class="grid lg:grid-cols-2 gap-6 mb-6">
                <!-- Por Servicio -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Ventas por Servicio</h2></div>
                    <?php if (empty($porServicio)): ?><div class="p-8 text-center text-slate-500">Sin datos</div>
                    <?php else: ?><div class="p-4 space-y-3">
                        <?php $max = max(array_column($porServicio, 'total') ?: [1]); foreach ($porServicio as $r): $pct = $max > 0 ? ($r['total']/$max)*100 : 0; ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span class="font-medium text-slate-700 dark:text-slate-300"><?php echo e($r['servicio']); ?></span><span class="text-slate-500"><?php echo $r['cantidad']; ?> - $<?php echo number_format($r['total'], 2); ?></span></div>
                            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded-full"><div class="h-2 bg-primary-500 rounded-full" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                        <?php endforeach; ?></div><?php endif; ?>
                </div>
                
                <!-- Por Vendedor -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Ranking Vendedores</h2></div>
                    <?php if (empty($porVendedor)): ?><div class="p-8 text-center text-slate-500">Sin datos</div>
                    <?php else: ?><div class="divide-y divide-light-border dark:divide-dark-border">
                        <?php $pos = 1; foreach ($porVendedor as $r): ?>
                        <div class="flex items-center gap-4 p-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold <?php echo $pos===1?'bg-yellow-100 text-yellow-600':($pos===2?'bg-slate-200 text-slate-500':($pos===3?'bg-amber-100 text-amber-600':'bg-slate-100 text-slate-400')); ?>"><?php echo $pos; ?></div>
                            <div class="flex-1"><p class="font-medium text-slate-800 dark:text-white"><?php echo e($r['vendedor']); ?></p><p class="text-xs text-slate-500"><?php echo $r['cantidad']; ?> ventas</p></div>
                            <p class="font-bold text-green-600">$<?php echo number_format($r['total'], 2); ?></p>
                        </div>
                        <?php $pos++; endforeach; ?></div><?php endif; ?>
                </div>
            </div>
            
            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Por Día -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Ultimos Dias</h2></div>
                    <?php if (empty($porDia)): ?><div class="p-8 text-center text-slate-500">Sin datos</div>
                    <?php else: ?><div class="p-4">
                        <div class="flex items-end justify-between gap-2 h-32">
                            <?php $max = max(array_column($porDia, 'total') ?: [1]); foreach ($porDia as $d): $h = $max > 0 ? ($d['total']/$max)*100 : 5; ?>
                            <div class="flex-1 flex flex-col items-center">
                                <div class="w-full bg-primary-500 rounded-t" style="height:<?php echo max($h, 5); ?>%"></div>
                                <p class="text-xs text-slate-500 mt-1"><?php echo date('d/m', strtotime($d['fecha'])); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div><?php endif; ?>
                </div>
                
                <!-- Top Clientes -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                    <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Top 5 Clientes</h2></div>
                    <?php if (empty($topClientes)): ?><div class="p-8 text-center text-slate-500">Sin datos</div>
                    <?php else: ?><div class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($topClientes as $c): ?>
                        <div class="flex items-center justify-between p-4">
                            <div><p class="font-medium text-slate-800 dark:text-white"><?php echo e($c['cliente']); ?></p><p class="text-xs text-slate-500"><?php echo $c['cantidad']; ?> compras</p></div>
                            <p class="font-bold text-green-600">$<?php echo number_format($c['total'], 2); ?></p>
                        </div>
                        <?php endforeach; ?></div><?php endif; ?>
                </div>
            </div>
            
        </main>
    </div>
</div>

<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>

<?php require_once '../includes/footer.php'; ?>