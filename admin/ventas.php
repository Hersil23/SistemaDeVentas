<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina.';
    } else {
        $accion = $_POST['accion'] ?? '';
        
        if ($accion === 'crear') {
            $cliente_id = sanitizeInt($_POST['cliente_id'] ?? 0);
            $perfil_id = sanitizeInt($_POST['perfil_id'] ?? 0);
            $duracion_meses = sanitizeInt($_POST['duracion_meses'] ?? 1);
            $precio_venta = sanitizeDecimal($_POST['precio_venta'] ?? 0);
            $notas = sanitizeInput($_POST['notas'] ?? '');
            $vendedor_id = getCurrentUserId();
            
            if ($cliente_id <= 0 || $perfil_id <= 0) {
                $error = 'Seleccione cliente y perfil';
            } else if ($duracion_meses < 1 || $duracion_meses > 12) {
                $error = 'Duracion debe ser entre 1 y 12 meses';
            } else if ($precio_venta <= 0) {
                $error = 'Ingrese un precio valido';
            } else {
                // Verificar que el perfil esté disponible
                $stmt = $conn->prepare("SELECT p.*, c.cuenta, c.password, c.servicio_id, s.nombre as servicio FROM perfiles p INNER JOIN cuentas c ON p.cuenta_id = c.id INNER JOIN servicios s ON c.servicio_id = s.id WHERE p.id = ? AND p.estado = 'disponible'");
                $stmt->bind_param("i", $perfil_id);
                $stmt->execute();
                $perfil = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$perfil) {
                    $error = 'El perfil seleccionado no esta disponible';
                } else {
                    $conn->begin_transaction();
                    try {
                        // Calcular fechas
                        $fecha_venta = date('Y-m-d');
                        $fecha_vencimiento = date('Y-m-d', strtotime("+$duracion_meses months"));
                        
                        // Insertar venta
                        $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, perfil_id, vendedor_id, precio_venta, duracion_meses, fecha_venta, fecha_vencimiento, notas, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activa')");
                        $stmt->bind_param("iiidisss", $cliente_id, $perfil_id, $vendedor_id, $precio_venta, $duracion_meses, $fecha_venta, $fecha_vencimiento, $notas);
                        $stmt->execute();
                        $venta_id = $conn->insert_id;
                        
                        // Actualizar perfil a vendido
                        $stmt = $conn->prepare("UPDATE perfiles SET estado = 'vendido' WHERE id = ?");
                        $stmt->bind_param("i", $perfil_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        $mensaje = 'Venta registrada correctamente. ID: ' . $venta_id;
                        logActivity('venta_creada', 'Venta ID: ' . $venta_id . ' - Perfil: ' . $perfil_id);
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al registrar la venta';
                    }
                }
            }
        }
        
        if ($accion === 'cancelar') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            
            // Obtener el perfil de la venta
            $stmt = $conn->prepare("SELECT perfil_id FROM ventas WHERE id = ? AND estado = 'activa'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $venta = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($venta) {
                $conn->begin_transaction();
                try {
                    // Cancelar venta
                    $stmt = $conn->prepare("UPDATE ventas SET estado = 'cancelada' WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    
                    // Liberar perfil
                    $stmt = $conn->prepare("UPDATE perfiles SET estado = 'disponible' WHERE id = ?");
                    $stmt->bind_param("i", $venta['perfil_id']);
                    $stmt->execute();
                    
                    $conn->commit();
                    $mensaje = 'Venta cancelada y perfil liberado';
                    logActivity('venta_cancelada', 'Venta ID: ' . $id);
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Error al cancelar la venta';
                }
            }
        }
    }
}

// Filtros
$filtro_servicio = sanitizeInt($_GET['servicio'] ?? 0);
$filtro_estado = sanitizeInput($_GET['estado'] ?? '');
$filtro_fecha = sanitizeInput($_GET['fecha'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if ($filtro_servicio > 0) { $where .= " AND c.servicio_id = ?"; $params[] = $filtro_servicio; $types .= "i"; }
if (!empty($filtro_estado)) { $where .= " AND v.estado = ?"; $params[] = $filtro_estado; $types .= "s"; }
if (!empty($filtro_fecha)) { $where .= " AND v.fecha_venta = ?"; $params[] = $filtro_fecha; $types .= "s"; }

$sql = "SELECT v.*, 
        cl.nombre as cliente_nombre, cl.apellido as cliente_apellido, cl.telefono as cliente_telefono,
        p.numero_perfil, c.cuenta, c.password as cuenta_password, 
        s.nombre as servicio,
        u.nombre as vendedor_nombre, u.apellido as vendedor_apellido
        FROM ventas v
        INNER JOIN clientes cl ON v.cliente_id = cl.id
        INNER JOIN perfiles p ON v.perfil_id = p.id
        INNER JOIN cuentas c ON p.cuenta_id = c.id
        INNER JOIN servicios s ON c.servicio_id = s.id
        INNER JOIN usuarios u ON v.vendedor_id = u.id
        WHERE $where
        ORDER BY v.fecha_venta DESC, v.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $ventas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Datos para el formulario
$clientes = $conn->query("SELECT * FROM clientes WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$servicios = $conn->query("SELECT * FROM servicios WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$todosServicios = $conn->query("SELECT * FROM servicios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Ventas';
require_once '../includes/header.php';
?>

<div class="min-h-screen flex bg-light-bg dark:bg-dark-bg">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-light-card dark:bg-dark-card border-r border-light-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="h-16 flex items-center gap-2 px-4 border-b border-light-border dark:border-dark-border">
            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
            <span class="text-lg font-bold text-slate-800 dark:text-white">SistemaDeVentas</span>
        </div>
        <nav class="p-4 space-y-1 pb-48 overflow-y-auto max-h-[calc(100vh-180px)]">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Dashboard</a>
            <a href="vendedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>Vendedores</a>
            <a href="servicios.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>Servicios</a>
            <a href="proveedores.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Proveedores</a>
            <a href="cuentas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Cuentas</a>
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Clientes</a>
            <a href="ventas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>Ventas</a>
            <a href="reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
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
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Ventas</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openModal()" class="flex items-center gap-2 px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span class="hidden sm:inline">Nueva Venta</span></button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
            <!-- Filtros -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4 mb-4">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <select name="servicio" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Todos los servicios</option>
                        <?php foreach ($todosServicios as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $filtro_servicio == $s['id'] ? 'selected' : ''; ?>><?php echo e($s['nombre']); ?></option><?php endforeach; ?>
                    </select>
                    <select name="estado" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Todos los estados</option>
                        <option value="activa" <?php echo $filtro_estado === 'activa' ? 'selected' : ''; ?>>Activa</option>
                        <option value="vencida" <?php echo $filtro_estado === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                        <option value="cancelada" <?php echo $filtro_estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                    <input type="date" name="fecha" value="<?php echo e($filtro_fecha); ?>" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                    <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg">Filtrar</button>
                    <?php if ($filtro_servicio || $filtro_estado || $filtro_fecha): ?><a href="ventas.php" class="px-4 py-2 text-slate-500 text-center">Limpiar</a><?php endif; ?>
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Historial de Ventas</h2><p class="text-sm text-slate-500"><?php echo count($ventas); ?> ventas</p></div>
                
                <?php if (empty($ventas)): ?>
                <div class="p-8 text-center"><p class="text-slate-500">No hay ventas registradas</p><button onclick="openModal()" class="mt-4 px-4 py-2 bg-green-500 text-white rounded-lg">Registrar primera venta</button></div>
                <?php else: ?>
                
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-800/50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Servicio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cuenta/Perfil</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Precio</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Vence</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($ventas as $v): 
                            $diasVence = (strtotime($v['fecha_vencimiento']) - time()) / 86400;
                            $claseVence = '';
                            if ($v['estado'] === 'activa') {
                                if ($diasVence < 0) $claseVence = 'text-red-600 dark:text-red-400';
                                elseif ($diasVence <= 7) $claseVence = 'text-amber-600 dark:text-amber-400';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente_nombre'] . ' ' . $v['cliente_apellido']); ?></span>
                                <?php if ($v['cliente_telefono']): ?><p class="text-xs text-slate-400"><?php echo e($v['cliente_telefono']); ?></p><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($v['servicio']); ?></td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-slate-600 dark:text-slate-300"><?php echo e($v['cuenta']); ?></span>
                                <p class="text-xs text-slate-400">Perfil <?php echo $v['numero_perfil']; ?></p>
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-green-600 dark:text-green-400">$<?php echo number_format($v['precio_venta'], 2); ?></td>
                            <td class="px-4 py-3 text-center text-sm <?php echo $claseVence; ?>"><?php echo date('d/m/Y', strtotime($v['fecha_vencimiento'])); ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php 
                                    echo $v['estado'] === 'activa' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : 
                                        ($v['estado'] === 'vencida' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' : 
                                        'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'); 
                                ?>"><?php echo ucfirst($v['estado']); ?></span>
                            </td>
                            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                                <button onclick="verDetalle(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-slate-500 hover:text-blue-500 rounded-lg" title="Ver detalle"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                                <button onclick="enviarWhatsApp(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-slate-500 hover:text-green-500 rounded-lg" title="Enviar WhatsApp"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></button>
                                <?php if ($v['estado'] === 'activa'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('¿Cancelar esta venta? El perfil quedara disponible nuevamente.')"><?php echo csrfField(); ?><input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id" value="<?php echo $v['id']; ?>"><button type="submit" class="p-2 text-slate-500 hover:text-red-500 rounded-lg" title="Cancelar venta"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Cards móvil -->
                <div class="lg:hidden divide-y divide-light-border dark:divide-dark-border">
                    <?php foreach ($ventas as $v): ?>
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente_nombre'] . ' ' . $v['cliente_apellido']); ?></h3>
                                <p class="text-sm text-slate-500"><?php echo e($v['servicio']); ?> - Perfil <?php echo $v['numero_perfil']; ?></p>
                            </div>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php 
                                echo $v['estado'] === 'activa' ? 'bg-green-100 text-green-600' : 
                                    ($v['estado'] === 'vencida' ? 'bg-amber-100 text-amber-600' : 'bg-red-100 text-red-600'); 
                            ?>"><?php echo ucfirst($v['estado']); ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-sm mb-3">
                            <span class="text-green-600 font-medium">$<?php echo number_format($v['precio_venta'], 2); ?></span>
                            <span class="text-slate-500">Vence: <?php echo date('d/m/Y', strtotime($v['fecha_vencimiento'])); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="verDetalle(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="flex-1 px-3 py-2 text-sm text-blue-600 bg-blue-50 rounded-lg">Ver Detalle</button>
                            <button onclick="enviarWhatsApp(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="flex-1 px-3 py-2 text-sm text-green-600 bg-green-50 rounded-lg">WhatsApp</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal Nueva Venta -->
<div id="modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 class="font-semibold text-slate-800 dark:text-white">Nueva Venta</h3><button onclick="closeModal()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="crear">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cliente *</label>
                    <select name="cliente_id" id="cliente_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Seleccionar cliente...</option>
                        <?php foreach ($clientes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo e($c['nombre'] . ' ' . $c['apellido'] . ($c['telefono'] ? ' - ' . $c['telefono'] : '')); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Servicio *</label>
                    <select id="servicio_venta" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" onchange="cargarCuentas()">
                        <option value="">Seleccionar servicio...</option>
                        <?php foreach ($servicios as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cuenta *</label>
                    <select id="cuenta_venta" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" onchange="cargarPerfiles()" disabled>
                        <option value="">Primero seleccione servicio</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Perfil *</label>
                    <select name="perfil_id" id="perfil_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" disabled>
                        <option value="">Primero seleccione cuenta</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Duracion (meses) *</label>
                        <select name="duracion_meses" id="duracion_meses" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?> mes<?php echo $i > 1 ? 'es' : ''; ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Precio (USD) *</label>
                        <input type="number" name="precio_venta" id="precio_venta" step="0.01" min="0.01" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="0.00">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notas</label>
                    <textarea name="notas" rows="2" maxlength="500" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button><button type="submit" class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg">Registrar Venta</button></div>
        </form>
    </div>
</div>

<!-- Modal Detalle -->
<div id="modalDetalle" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModalDetalle()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 class="font-semibold text-slate-800 dark:text-white">Detalle de Venta</h3><button onclick="closeModalDetalle()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <div id="detalleContent" class="p-4"></div>
    </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}
function openModal(){document.getElementById('modal').classList.remove('hidden');}
function closeModal(){document.getElementById('modal').classList.add('hidden');}
function closeModalDetalle(){document.getElementById('modalDetalle').classList.remove('hidden');}

function cargarCuentas() {
    const servicioId = document.getElementById('servicio_venta').value;
    const cuentaSelect = document.getElementById('cuenta_venta');
    const perfilSelect = document.getElementById('perfil_id');
    
    cuentaSelect.innerHTML = '<option value="">Cargando...</option>';
    cuentaSelect.disabled = true;
    perfilSelect.innerHTML = '<option value="">Primero seleccione cuenta</option>';
    perfilSelect.disabled = true;
    
    if (!servicioId) {
        cuentaSelect.innerHTML = '<option value="">Primero seleccione servicio</option>';
        return;
    }
    
    fetch('ajax/get_cuentas.php?servicio_id=' + servicioId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.cuentas.length > 0) {
                cuentaSelect.innerHTML = '<option value="">Seleccionar cuenta...</option>';
                data.cuentas.forEach(c => {
                    cuentaSelect.innerHTML += `<option value="${c.id}">${c.cuenta} (${c.disponibles} disp.)</option>`;
                });
                cuentaSelect.disabled = false;
            } else {
                cuentaSelect.innerHTML = '<option value="">No hay cuentas disponibles</option>';
            }
        });
}

function cargarPerfiles() {
    const cuentaId = document.getElementById('cuenta_venta').value;
    const perfilSelect = document.getElementById('perfil_id');
    
    perfilSelect.innerHTML = '<option value="">Cargando...</option>';
    perfilSelect.disabled = true;
    
    if (!cuentaId) {
        perfilSelect.innerHTML = '<option value="">Primero seleccione cuenta</option>';
        return;
    }
    
    fetch('ajax/get_perfiles.php?cuenta_id=' + cuentaId + '&disponibles=1')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.perfiles.length > 0) {
                perfilSelect.innerHTML = '<option value="">Seleccionar perfil...</option>';
                data.perfiles.forEach(p => {
                    perfilSelect.innerHTML += `<option value="${p.id}">Perfil ${p.numero_perfil}</option>`;
                });
                perfilSelect.disabled = false;
            } else {
                perfilSelect.innerHTML = '<option value="">No hay perfiles disponibles</option>';
            }
        });
}

function verDetalle(v) {
    const html = `
        <div class="space-y-4">
            <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                <p class="text-xs text-slate-500 mb-1">Cliente</p>
                <p class="font-medium text-slate-800 dark:text-white">${v.cliente_nombre} ${v.cliente_apellido}</p>
                ${v.cliente_telefono ? `<p class="text-sm text-slate-500">${v.cliente_telefono}</p>` : ''}
            </div>
            <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                <p class="text-xs text-slate-500 mb-1">Servicio</p>
                <p class="font-medium text-slate-800 dark:text-white">${v.servicio}</p>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-1">Datos de Acceso</p>
                <p class="font-medium text-slate-800 dark:text-white">${v.cuenta}</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Pass: ${v.cuenta_password || '-'}</p>
                <p class="text-sm text-slate-600 dark:text-slate-300">Perfil: ${v.numero_perfil}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Precio</p>
                    <p class="font-medium text-green-600">$${parseFloat(v.precio_venta).toFixed(2)}</p>
                </div>
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Duracion</p>
                    <p class="font-medium text-slate-800 dark:text-white">${v.duracion_meses} mes${v.duracion_meses > 1 ? 'es' : ''}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Fecha Venta</p>
                    <p class="font-medium text-slate-800 dark:text-white">${new Date(v.fecha_venta).toLocaleDateString('es')}</p>
                </div>
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                    <p class="text-xs text-slate-500 mb-1">Vencimiento</p>
                    <p class="font-medium text-slate-800 dark:text-white">${new Date(v.fecha_vencimiento).toLocaleDateString('es')}</p>
                </div>
            </div>
            <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg">
                <p class="text-xs text-slate-500 mb-1">Vendedor</p>
                <p class="font-medium text-slate-800 dark:text-white">${v.vendedor_nombre} ${v.vendedor_apellido}</p>
            </div>
            ${v.notas ? `<div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500 mb-1">Notas</p><p class="text-sm text-slate-600 dark:text-slate-300">${v.notas}</p></div>` : ''}
        </div>
    `;
    document.getElementById('detalleContent').innerHTML = html;
    document.getElementById('modalDetalle').classList.remove('hidden');
}

function enviarWhatsApp(v) {
    const telefono = v.cliente_telefono ? v.cliente_telefono.replace(/[^0-9]/g, '') : '';
    if (!telefono) {
        alert('El cliente no tiene telefono registrado');
        return;
    }
    
    const mensaje = `Hola ${v.cliente_nombre}!

Aqui estan los datos de tu cuenta de *${v.servicio}*:

Correo: ${v.cuenta}
Contrasena: ${v.cuenta_password || '-'}
Perfil: ${v.numero_perfil}

Fecha de vencimiento: ${new Date(v.fecha_vencimiento).toLocaleDateString('es')}

Gracias por tu compra!`;

    window.open(`https://wa.me/${telefono}?text=${encodeURIComponent(mensaje)}`, '_blank');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeModalDetalle(); } });
</script>

<?php require_once '../includes/footer.php'; ?>