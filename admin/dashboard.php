<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar que sea admin
requireAdmin();

// Obtener estadísticas
// Ventas del día
$hoy = date('Y-m-d');
$stmtVentasHoy = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(precio_venta), 0) as monto FROM ventas WHERE DATE(fecha_venta) = '$hoy'");
$ventasHoy = $stmtVentasHoy->fetch_assoc();

// Ventas del mes
$mesActual = date('Y-m');
$stmtVentasMes = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(precio_venta), 0) as monto FROM ventas WHERE DATE_FORMAT(fecha_venta, '%Y-%m') = '$mesActual'");
$ventasMes = $stmtVentasMes->fetch_assoc();

// Total clientes activos
$stmtClientes = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 'activo'");
$totalClientes = $stmtClientes->fetch_assoc()['total'];

// Total vendedores activos
$stmtVendedores = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'vendedor' AND estado = 'activo'");
$totalVendedores = $stmtVendedores->fetch_assoc()['total'];

// Perfiles disponibles
$stmtPerfilesDisp = $conn->query("SELECT COUNT(*) as total FROM perfiles WHERE estado = 'disponible'");
$perfilesDisponibles = $stmtPerfilesDisp->fetch_assoc()['total'];

// Perfiles vendidos
$stmtPerfilesVend = $conn->query("SELECT COUNT(*) as total FROM perfiles WHERE estado = 'vendido'");
$perfilesVendidos = $stmtPerfilesVend->fetch_assoc()['total'];

// Cuentas activas
$stmtCuentasAct = $conn->query("SELECT COUNT(*) as total FROM cuentas WHERE estado = 'activa'");
$cuentasActivas = $stmtCuentasAct->fetch_assoc()['total'];

// Inventario por servicio
$stmtInventario = $conn->query("
    SELECT 
        s.nombre as servicio,
        COUNT(CASE WHEN p.estado = 'disponible' THEN 1 END) as disponibles,
        COUNT(CASE WHEN p.estado = 'vendido' THEN 1 END) as vendidos,
        COUNT(CASE WHEN p.estado = 'vencido' THEN 1 END) as vencidos
    FROM servicios s
    LEFT JOIN cuentas c ON c.servicio_id = s.id
    LEFT JOIN perfiles p ON p.cuenta_id = c.id
    WHERE s.estado = 'activo'
    GROUP BY s.id, s.nombre
    ORDER BY s.nombre
");
$inventario = $stmtInventario->fetch_all(MYSQLI_ASSOC);

// Ventas por vencer (próximos 7 días)
$fecha7dias = date('Y-m-d', strtotime('+7 days'));
$stmtPorVencer = $conn->query("
    SELECT 
        v.id,
        CONCAT(c.nombre, ' ', c.apellido) as cliente,
        s.nombre as servicio,
        v.fecha_vencimiento,
        DATEDIFF(v.fecha_vencimiento, CURDATE()) as dias_restantes
    FROM ventas v
    INNER JOIN clientes c ON v.cliente_id = c.id
    INNER JOIN perfiles p ON v.perfil_id = p.id
    INNER JOIN cuentas cu ON p.cuenta_id = cu.id
    INNER JOIN servicios s ON cu.servicio_id = s.id
    WHERE v.estado = 'activa' 
    AND v.fecha_vencimiento BETWEEN CURDATE() AND '$fecha7dias'
    ORDER BY v.fecha_vencimiento ASC
    LIMIT 10
");
$ventasPorVencer = $stmtPorVencer->fetch_all(MYSQLI_ASSOC);

// Ventas vencidas
$stmtVencidas = $conn->query("
    SELECT COUNT(*) as total 
    FROM ventas 
    WHERE estado = 'activa' AND fecha_vencimiento < CURDATE()
");
$ventasVencidas = $stmtVencidas->fetch_assoc()['total'];

// Cuentas por vencer (próximos 7 días)
$stmtCuentasPorVencer = $conn->query("
    SELECT COUNT(*) as total 
    FROM cuentas 
    WHERE estado = 'activa' AND fecha_vencimiento BETWEEN CURDATE() AND '$fecha7dias'
");
$cuentasPorVencer = $stmtCuentasPorVencer->fetch_assoc()['total'];

$pageTitle = 'Dashboard';
require_once '../includes/header.php';
?>

<div class="min-h-screen flex bg-light-bg dark:bg-dark-bg">
    
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-light-card dark:bg-dark-card border-r border-light-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        
        <!-- Logo -->
        <div class="h-16 flex items-center gap-2 px-4 border-b border-light-border dark:border-dark-border">
            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
        </div>
        
        <!-- Menú -->
        <nav class="p-4 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            
            <a href="vendedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Vendedores
            </a>
            
            <a href="servicios.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
                Servicios
            </a>
            
            <a href="proveedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Proveedores
            </a>
            
            <a href="cuentas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Cuentas
            </a>
            
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Clientes
            </a>
            
            <a href="ventas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Ventas
            </a>
            
            <a href="reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Reportes
            </a>
            
            <a href="configuracion.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Configuracion
            </a>
            
        </nav>
        
        <!-- Usuario y cerrar sesión -->
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-light-border dark:border-dark-border">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold">
                    <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800 dark:text-white truncate"><?php echo e(getCurrentUserName()); ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Administrador</p>
                </div>
            </div>
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Cerrar Sesion
            </a>
        </div>
        
    </aside>
    
    <!-- Overlay para móvil -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>
    
    <!-- Contenido principal -->
    <div class="flex-1 lg:ml-64">
        
        <!-- Header -->
        <header class="h-16 bg-light-card dark:bg-dark-card border-b border-light-border dark:border-dark-border flex items-center justify-between px-4 lg:px-6">
            
            <!-- Botón menú móvil -->
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            
            <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Dashboard</h1>
            
            <!-- Toggle Dark Mode -->
            <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
            
        </header>
        
        <!-- Contenido -->
        <main class="p-4 lg:p-6">
            
            <!-- Estadísticas principales -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                
                <!-- Ventas hoy -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Ventas Hoy</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white">$<?php echo number_format($ventasHoy['monto'], 2); ?></p>
                            <p class="text-xs text-slate-400"><?php echo $ventasHoy['total']; ?> ventas</p>
                        </div>
                    </div>
                </div>
                
                <!-- Ventas mes -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Ventas Mes</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white">$<?php echo number_format($ventasMes['monto'], 2); ?></p>
                            <p class="text-xs text-slate-400"><?php echo $ventasMes['total']; ?> ventas</p>
                        </div>
                    </div>
                </div>
                
                <!-- Clientes -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Clientes</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white"><?php echo $totalClientes; ?></p>
                            <p class="text-xs text-slate-400">activos</p>
                        </div>
                    </div>
                </div>
                
                <!-- Vendedores -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-600 dark:text-orange-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Vendedores</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white"><?php echo $totalVendedores; ?></p>
                            <p class="text-xs text-slate-400">activos</p>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Segunda fila de estadísticas -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                
                <!-- Perfiles disponibles -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Perfiles Disponibles</p>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400"><?php echo $perfilesDisponibles; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Perfiles vendidos -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600 dark:text-red-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Perfiles Vendidos</p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400"><?php echo $perfilesVendidos; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Cuentas activas -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center text-cyan-600 dark:text-cyan-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Cuentas Activas</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white"><?php echo $cuentasActivas; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Alertas -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl p-4 border border-light-border dark:border-dark-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Alertas</p>
                            <p class="text-lg font-bold text-amber-600 dark:text-amber-400"><?php echo $ventasVencidas + $cuentasPorVencer; ?></p>
                            <p class="text-xs text-slate-400"><?php echo $ventasVencidas; ?> vencidas, <?php echo $cuentasPorVencer; ?> por vencer</p>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Inventario y Alertas -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Inventario por servicio -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border">
                    <div class="p-4 border-b border-light-border dark:border-dark-border">
                        <h2 class="font-semibold text-slate-800 dark:text-white">Inventario por Servicio</h2>
                    </div>
                    <div class="p-4">
                        <?php if (empty($inventario)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">No hay servicios registrados</p>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($inventario as $item): ?>
                            <div class="flex items-center justify-between p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                                <span class="font-medium text-slate-800 dark:text-white"><?php echo e($item['servicio']); ?></span>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="px-2 py-1 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400"><?php echo $item['disponibles']; ?> disp.</span>
                                    <span class="px-2 py-1 rounded bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400"><?php echo $item['vendidos']; ?> vend.</span>
                                    <span class="px-2 py-1 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400"><?php echo $item['vencidos']; ?> venc.</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Próximos a vencer -->
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border">
                    <div class="p-4 border-b border-light-border dark:border-dark-border">
                        <h2 class="font-semibold text-slate-800 dark:text-white">Proximos a Vencer (7 dias)</h2>
                    </div>
                    <div class="p-4">
                        <?php if (empty($ventasPorVencer)): ?>
                        <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">No hay ventas proximas a vencer</p>
                        <?php else: ?>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <?php foreach ($ventasPorVencer as $venta): ?>
                            <div class="flex items-center justify-between p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                                <div>
                                    <p class="font-medium text-slate-800 dark:text-white text-sm"><?php echo e($venta['cliente']); ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($venta['servicio']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm <?php echo $venta['dias_restantes'] <= 2 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'; ?> font-medium">
                                        <?php echo $venta['dias_restantes']; ?> dias
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo date('d/m/Y', strtotime($venta['fecha_vencimiento'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>