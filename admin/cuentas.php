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
            $servicio_id = sanitizeInt($_POST['servicio_id'] ?? 0);
            $proveedor_id = sanitizeInt($_POST['proveedor_id'] ?? 0);
            $cuenta = sanitizeInput($_POST['cuenta'] ?? '');
            $password = sanitizeInput($_POST['password_cuenta'] ?? '');
            $total_perfiles = sanitizeInt($_POST['total_perfiles'] ?? 1);
            $costo_compra = sanitizeDecimal($_POST['costo_compra'] ?? 0);
            $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
            
            if (empty($cuenta) || $servicio_id <= 0 || $proveedor_id <= 0) {
                $error = 'Complete los campos obligatorios';
            } else if ($total_perfiles < 1 || $total_perfiles > 6) {
                $error = 'El numero de perfiles debe ser entre 1 y 6';
            } else {
                $stmt = $conn->prepare("SELECT id FROM cuentas WHERE cuenta = ?");
                $stmt->bind_param("s", $cuenta);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Ya existe una cuenta con ese correo';
                } else {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO cuentas (servicio_id, proveedor_id, cuenta, password, total_perfiles, costo_compra, fecha_compra, fecha_vencimiento, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activa')");
                        $stmt->bind_param("iissidss", $servicio_id, $proveedor_id, $cuenta, $password, $total_perfiles, $costo_compra, $fecha_compra, $fecha_vencimiento);
                        $stmt->execute();
                        $cuenta_id = $conn->insert_id;
                        
                        $stmtPerfil = $conn->prepare("INSERT INTO perfiles (cuenta_id, numero_perfil, estado) VALUES (?, ?, 'disponible')");
                        for ($i = 1; $i <= $total_perfiles; $i++) {
                            $stmtPerfil->bind_param("ii", $cuenta_id, $i);
                            $stmtPerfil->execute();
                        }
                        
                        $conn->commit();
                        $mensaje = 'Cuenta creada con ' . $total_perfiles . ' perfiles';
                        logActivity('cuenta_creada', 'Cuenta: ' . $cuenta);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al crear la cuenta';
                    }
                }
                $stmt->close();
            }
        }
        
        if ($accion === 'editar') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            $servicio_id = sanitizeInt($_POST['servicio_id'] ?? 0);
            $proveedor_id = sanitizeInt($_POST['proveedor_id'] ?? 0);
            $cuenta = sanitizeInput($_POST['cuenta'] ?? '');
            $password = sanitizeInput($_POST['password_cuenta'] ?? '');
            $costo_compra = sanitizeDecimal($_POST['costo_compra'] ?? 0);
            $fecha_compra = $_POST['fecha_compra'] ?? '';
            $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
            
            if (empty($cuenta) || $id <= 0) {
                $error = 'Datos incompletos';
            } else {
                $stmt = $conn->prepare("SELECT id FROM cuentas WHERE cuenta = ? AND id != ?");
                $stmt->bind_param("si", $cuenta, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Ya existe otra cuenta con ese correo';
                } else {
                    $stmt = $conn->prepare("UPDATE cuentas SET servicio_id=?, proveedor_id=?, cuenta=?, password=?, costo_compra=?, fecha_compra=?, fecha_vencimiento=? WHERE id=?");
                    $stmt->bind_param("iissdssi", $servicio_id, $proveedor_id, $cuenta, $password, $costo_compra, $fecha_compra, $fecha_vencimiento, $id);
                    if ($stmt->execute()) {
                        $mensaje = 'Cuenta actualizada';
                        logActivity('cuenta_editada', 'Cuenta ID: ' . $id);
                    } else {
                        $error = 'Error al actualizar';
                    }
                }
                $stmt->close();
            }
        }
        
        if ($accion === 'cambiar_estado') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            $estado = $_POST['estado'] === 'activa' ? 'vencida' : 'activa';
            $stmt = $conn->prepare("UPDATE cuentas SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $estado, $id);
            if ($stmt->execute()) {
                $mensaje = 'Estado actualizado';
            }
            $stmt->close();
        }
    }
}

$filtro_servicio = sanitizeInt($_GET['servicio'] ?? 0);
$filtro_estado = sanitizeInput($_GET['estado'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if ($filtro_servicio > 0) { $where .= " AND c.servicio_id = ?"; $params[] = $filtro_servicio; $types .= "i"; }
if (!empty($filtro_estado)) { $where .= " AND c.estado = ?"; $params[] = $filtro_estado; $types .= "s"; }

$sql = "SELECT c.*, s.nombre as servicio, p.nombre as proveedor,
        (SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND estado = 'disponible') as perfiles_disponibles,
        (SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND estado = 'vendido') as perfiles_vendidos
        FROM cuentas c INNER JOIN servicios s ON c.servicio_id = s.id INNER JOIN proveedores p ON c.proveedor_id = p.id
        WHERE $where ORDER BY c.fecha_vencimiento ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $cuentas = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$servicios = $conn->query("SELECT * FROM servicios WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$proveedores = $conn->query("SELECT * FROM proveedores WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$todosServicios = $conn->query("SELECT * FROM servicios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Cuentas';
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
            <a href="cuentas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Cuentas</a>
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Clientes</a>
            <a href="ventas.php" class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>Ventas</a>
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
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Cuentas</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openModal('crear')" class="flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span class="hidden sm:inline">Nueva Cuenta</span></button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
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
                    </select>
                    <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg">Filtrar</button>
                    <?php if ($filtro_servicio || $filtro_estado): ?><a href="cuentas.php" class="px-4 py-2 text-slate-500 text-center">Limpiar</a><?php endif; ?>
                </form>
            </div>
            
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Lista de Cuentas</h2><p class="text-sm text-slate-500"><?php echo count($cuentas); ?> cuentas</p></div>
                
                <?php if (empty($cuentas)): ?>
                <div class="p-8 text-center"><p class="text-slate-500">No hay cuentas</p><button onclick="openModal('crear')" class="mt-4 px-4 py-2 bg-primary-500 text-white rounded-lg">Crear primera cuenta</button></div>
                <?php else: ?>
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-800/50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cuenta</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Servicio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Proveedor</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Perfiles</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Vence</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($cuentas as $c): $diasVence = $c['fecha_vencimiento'] ? (strtotime($c['fecha_vencimiento']) - time()) / 86400 : null; $claseVence = ''; if ($diasVence !== null) { if ($diasVence < 0) $claseVence = 'text-red-600 dark:text-red-400'; elseif ($diasVence <= 7) $claseVence = 'text-amber-600 dark:text-amber-400'; } ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3"><span class="font-medium text-slate-800 dark:text-white"><?php echo e($c['cuenta']); ?></span><p class="text-xs text-slate-400">Pass: <?php echo e($c['password']); ?></p></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($c['servicio']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($c['proveedor']); ?></td>
                            <td class="px-4 py-3 text-center"><button onclick="verPerfiles(<?php echo $c['id']; ?>)" class="inline-flex items-center gap-1 text-sm"><span class="px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400"><?php echo $c['perfiles_disponibles']; ?></span><span class="text-slate-400">/</span><span class="px-2 py-1 rounded bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400"><?php echo $c['perfiles_vendidos']; ?></span></button></td>
                            <td class="px-4 py-3 text-center text-sm <?php echo $claseVence; ?>"><?php echo $c['fecha_vencimiento'] ? date('d/m/Y', strtotime($c['fecha_vencimiento'])) : '-'; ?></td>
                            <td class="px-4 py-3 text-center"><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $c['estado'] === 'activa' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'; ?>"><?php echo ucfirst($c['estado']); ?></span></td>
                            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                                <button onclick="verPerfiles(<?php echo $c['id']; ?>)" class="p-2 text-slate-500 hover:text-blue-500 rounded-lg" title="Ver perfiles"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                                <button onclick="openModal('editar', <?php echo htmlspecialchars(json_encode($c)); ?>)" class="p-2 text-slate-500 hover:text-primary-500 rounded-lg" title="Editar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                <form method="POST" class="inline" onsubmit="return confirm('¿Cambiar estado?')"><?php echo csrfField(); ?><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="estado" value="<?php echo $c['estado']; ?>"><button type="submit" class="p-2 text-slate-500 hover:text-amber-500 rounded-lg" title="Cambiar estado"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg></button></form>
                            </div></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="lg:hidden divide-y divide-light-border dark:divide-dark-border">
                    <?php foreach ($cuentas as $c): ?>
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div><h3 class="font-medium text-slate-800 dark:text-white"><?php echo e($c['cuenta']); ?></h3><p class="text-xs text-slate-400">Pass: <?php echo e($c['password']); ?></p><p class="text-sm text-slate-500 mt-1"><?php echo e($c['servicio']); ?> - <?php echo e($c['proveedor']); ?></p></div>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $c['estado'] === 'activa' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>"><?php echo ucfirst($c['estado']); ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-sm mb-3"><span class="text-green-600"><?php echo $c['perfiles_disponibles']; ?> disp.</span><span class="text-red-600"><?php echo $c['perfiles_vendidos']; ?> vend.</span></div>
                        <div class="flex items-center gap-2">
                            <button onclick="verPerfiles(<?php echo $c['id']; ?>)" class="flex-1 px-3 py-2 text-sm text-blue-600 bg-blue-50 rounded-lg">Ver Perfiles</button>
                            <button onclick="openModal('editar', <?php echo htmlspecialchars(json_encode($c)); ?>)" class="flex-1 px-3 py-2 text-sm text-primary-600 bg-primary-50 rounded-lg">Editar</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div id="modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 id="modalTitle" class="font-semibold text-slate-800 dark:text-white">Nueva Cuenta</h3><button onclick="closeModal()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" id="formAccion" value="crear">
            <input type="hidden" name="id" id="formId" value="">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Servicio *</label><select name="servicio_id" id="servicio_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"><option value="">Seleccionar...</option><?php foreach ($servicios as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['nombre']); ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Proveedor *</label><select name="proveedor_id" id="proveedor_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"><option value="">Seleccionar...</option><?php foreach ($proveedores as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo e($p['nombre']); ?></option><?php endforeach; ?></select></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Correo/Usuario *</label><input type="text" name="cuenta" id="cuenta" required maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="correo@ejemplo.com"></div>
                <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Contrasena</label><input type="text" name="password_cuenta" id="password_cuenta" maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
                <div id="perfilesContainer"><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Perfiles (1-6) *</label><input type="number" name="total_perfiles" id="total_perfiles" min="1" max="6" value="1" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Costo (USD)</label><input type="number" name="costo_compra" id="costo_compra" step="0.01" min="0" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="0.00"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha compra</label><input type="date" name="fecha_compra" id="fecha_compra" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha vencimiento</label><input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button><button type="submit" class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg">Guardar</button></div>
        </form>
    </div>
</div>

<div id="modalPerfiles" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModalPerfiles()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 class="font-semibold text-slate-800 dark:text-white">Perfiles de la Cuenta</h3><button onclick="closeModalPerfiles()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <div id="perfilesContent" class="p-4"><p class="text-center text-slate-500">Cargando...</p></div>
    </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}
function openModal(tipo,data=null){const m=document.getElementById('modal');document.getElementById('formId').value='';document.getElementById('servicio_id').value='';document.getElementById('proveedor_id').value='';document.getElementById('cuenta').value='';document.getElementById('password_cuenta').value='';document.getElementById('total_perfiles').value='1';document.getElementById('costo_compra').value='';document.getElementById('fecha_compra').value='<?php echo date('Y-m-d'); ?>';document.getElementById('fecha_vencimiento').value='';if(tipo==='crear'){document.getElementById('modalTitle').textContent='Nueva Cuenta';document.getElementById('formAccion').value='crear';document.getElementById('perfilesContainer').style.display='block';}else{document.getElementById('modalTitle').textContent='Editar Cuenta';document.getElementById('formAccion').value='editar';document.getElementById('perfilesContainer').style.display='none';document.getElementById('formId').value=data.id;document.getElementById('servicio_id').value=data.servicio_id;document.getElementById('proveedor_id').value=data.proveedor_id;document.getElementById('cuenta').value=data.cuenta;document.getElementById('password_cuenta').value=data.password||'';document.getElementById('costo_compra').value=data.costo_compra||'';document.getElementById('fecha_compra').value=data.fecha_compra||'';document.getElementById('fecha_vencimiento').value=data.fecha_vencimiento||'';}m.classList.remove('hidden');}
function closeModal(){document.getElementById('modal').classList.add('hidden');}
function verPerfiles(cuentaId){document.getElementById('modalPerfiles').classList.remove('hidden');document.getElementById('perfilesContent').innerHTML='<p class="text-center text-slate-500">Cargando...</p>';fetch('ajax/get_perfiles.php?cuenta_id='+cuentaId).then(r=>r.json()).then(data=>{if(data.success){let html='<div class="space-y-2">';data.perfiles.forEach(p=>{const cls=p.estado==='disponible'?'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400':'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400';html+=`<div class="flex items-center justify-between p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><div class="flex items-center gap-3"><span class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-sm font-medium text-slate-600 dark:text-slate-300">${p.numero_perfil}</span><span class="text-sm font-medium text-slate-800 dark:text-white">Perfil ${p.numero_perfil}</span></div><span class="px-2 py-1 text-xs font-medium rounded-full ${cls}">${p.estado.charAt(0).toUpperCase()+p.estado.slice(1)}</span></div>`;});html+='</div>';document.getElementById('perfilesContent').innerHTML=html;}else{document.getElementById('perfilesContent').innerHTML='<p class="text-center text-red-500">Error</p>';}}).catch(()=>{document.getElementById('perfilesContent').innerHTML='<p class="text-center text-red-500">Error de conexion</p>';});}
function closeModalPerfiles(){document.getElementById('modalPerfiles').classList.add('hidden');}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();closeModalPerfiles();}});
</script>

<?php require_once '../includes/footer.php'; ?>