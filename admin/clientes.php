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
            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $apellido = sanitizeInput($_POST['apellido'] ?? '');
            $telefono = sanitizeInput($_POST['telefono'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $notas = sanitizeInput($_POST['notas'] ?? '');
            
            if (empty($nombre) || empty($telefono)) {
                $error = 'Nombre y telefono son obligatorios';
            } else {
                $stmt = $conn->prepare("INSERT INTO clientes (nombre, apellido, telefono, email, notas, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
                $stmt->bind_param("sssss", $nombre, $apellido, $telefono, $email, $notas);
                if ($stmt->execute()) {
                    $mensaje = 'Cliente creado correctamente';
                    logActivity('cliente_creado', 'Cliente: ' . $nombre . ' ' . $apellido);
                } else {
                    $error = 'Error al crear el cliente';
                }
                $stmt->close();
            }
        }
        
        if ($accion === 'editar') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $apellido = sanitizeInput($_POST['apellido'] ?? '');
            $telefono = sanitizeInput($_POST['telefono'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $notas = sanitizeInput($_POST['notas'] ?? '');
            
            if (empty($nombre) || empty($telefono) || $id <= 0) {
                $error = 'Datos incompletos';
            } else {
                $stmt = $conn->prepare("UPDATE clientes SET nombre=?, apellido=?, telefono=?, email=?, notas=? WHERE id=?");
                $stmt->bind_param("sssssi", $nombre, $apellido, $telefono, $email, $notas, $id);
                if ($stmt->execute()) {
                    $mensaje = 'Cliente actualizado correctamente';
                    logActivity('cliente_editado', 'Cliente ID: ' . $id);
                } else {
                    $error = 'Error al actualizar';
                }
                $stmt->close();
            }
        }
        
        if ($accion === 'cambiar_estado') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            $estado = $_POST['estado'] === 'activo' ? 'inactivo' : 'activo';
            $stmt = $conn->prepare("UPDATE clientes SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $estado, $id);
            if ($stmt->execute()) {
                $mensaje = 'Estado actualizado';
            }
            $stmt->close();
        }
    }
}

// Búsqueda
$busqueda = sanitizeInput($_GET['q'] ?? '');

$sql = "SELECT c.*, (SELECT COUNT(*) FROM ventas WHERE cliente_id = c.id) as total_ventas 
        FROM clientes c WHERE 1=1";
$params = [];
$types = "";

if (!empty($busqueda)) {
    $sql .= " AND (c.nombre LIKE ? OR c.apellido LIKE ? OR c.telefono LIKE ? OR c.email LIKE ?)";
    $like = "%$busqueda%";
    $params = [$like, $like, $like, $like];
    $types = "ssss";
}

$sql .= " ORDER BY c.nombre ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $clientes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Clientes';
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
            <a href="clientes.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Clientes</a>
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
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Clientes</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openModal('crear')" class="flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span class="hidden sm:inline">Nuevo Cliente</span></button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
            <!-- Búsqueda -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4 mb-4">
                <form method="GET" class="flex gap-3">
                    <div class="flex-1 relative">
                        <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="q" value="<?php echo e($busqueda); ?>" placeholder="Buscar por nombre, telefono o email..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600">Buscar</button>
                    <?php if (!empty($busqueda)): ?><a href="clientes.php" class="px-4 py-2 text-slate-500 hover:text-slate-700">Limpiar</a><?php endif; ?>
                </form>
            </div>
            
            <!-- Tabla -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                <div class="p-4 border-b border-light-border dark:border-dark-border"><h2 class="font-semibold text-slate-800 dark:text-white">Lista de Clientes</h2><p class="text-sm text-slate-500"><?php echo count($clientes); ?> clientes<?php echo !empty($busqueda) ? ' encontrados' : ''; ?></p></div>
                
                <?php if (empty($clientes)): ?>
                <div class="p-8 text-center"><p class="text-slate-500">No hay clientes<?php echo !empty($busqueda) ? ' con esa busqueda' : ''; ?></p>
                <?php if (empty($busqueda)): ?><button onclick="openModal('crear')" class="mt-4 px-4 py-2 bg-primary-500 text-white rounded-lg">Crear primer cliente</button><?php endif; ?>
                </div>
                <?php else: ?>
                
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-800/50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Telefono</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Ventas</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($clientes as $c): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3"><span class="font-medium text-slate-800 dark:text-white"><?php echo e($c['nombre'] . ' ' . $c['apellido']); ?></span></td>
                            <td class="px-4 py-3">
                                <?php if ($c['telefono']): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $c['telefono']); ?>" target="_blank" class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 hover:underline text-sm">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    <?php echo e($c['telefono']); ?>
                                </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($c['email'] ?: '-'); ?></td>
                            <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-medium"><?php echo $c['total_ventas']; ?></span></td>
                            <td class="px-4 py-3 text-center"><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $c['estado'] === 'activo' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'; ?>"><?php echo ucfirst($c['estado']); ?></span></td>
                            <td class="px-4 py-3"><div class="flex items-center justify-center gap-1">
                                <button onclick="openModal('editar', <?php echo htmlspecialchars(json_encode($c)); ?>)" class="p-2 text-slate-500 hover:text-primary-500 rounded-lg" title="Editar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                <form method="POST" class="inline" onsubmit="return confirm('¿Cambiar estado?')"><?php echo csrfField(); ?><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="estado" value="<?php echo $c['estado']; ?>"><button type="submit" class="p-2 text-slate-500 hover:text-amber-500 rounded-lg" title="Cambiar estado"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg></button></form>
                            </div></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="md:hidden divide-y divide-light-border dark:divide-dark-border">
                    <?php foreach ($clientes as $c): ?>
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div><h3 class="font-medium text-slate-800 dark:text-white"><?php echo e($c['nombre'] . ' ' . $c['apellido']); ?></h3>
                            <?php if ($c['telefono']): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $c['telefono']); ?>" target="_blank" class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 text-sm mt-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                <?php echo e($c['telefono']); ?>
                            </a>
                            <?php endif; ?>
                            </div>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $c['estado'] === 'activo' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>"><?php echo ucfirst($c['estado']); ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-slate-500 mb-3">
                            <?php if ($c['email']): ?><span><?php echo e($c['email']); ?></span><?php endif; ?>
                            <span class="text-blue-600"><?php echo $c['total_ventas']; ?> ventas</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="openModal('editar', <?php echo htmlspecialchars(json_encode($c)); ?>)" class="flex-1 px-3 py-2 text-sm text-primary-600 bg-primary-50 rounded-lg">Editar</button>
                            <form method="POST" class="flex-1" onsubmit="return confirm('¿Cambiar estado?')"><?php echo csrfField(); ?><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="estado" value="<?php echo $c['estado']; ?>"><button type="submit" class="w-full px-3 py-2 text-sm text-amber-600 bg-amber-50 rounded-lg"><?php echo $c['estado'] === 'activo' ? 'Desactivar' : 'Activar'; ?></button></form>
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
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 id="modalTitle" class="font-semibold text-slate-800 dark:text-white">Nuevo Cliente</h3><button onclick="closeModal()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" id="formAccion" value="crear">
            <input type="hidden" name="id" id="formId" value="">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre *</label><input type="text" name="nombre" id="nombre" required maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500"></div>
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Apellido</label><input type="text" name="apellido" id="apellido" maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500"></div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Telefono / WhatsApp *</label><input type="text" name="telefono" id="telefono" required maxlength="20" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="+584141234567"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label><input type="email" name="email" id="email" maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notas</label><textarea name="notas" id="notas" rows="2" maxlength="500" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"></textarea></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button><button type="submit" class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg">Guardar</button></div>
        </form>
    </div>
</div>

<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}
function openModal(tipo,data=null){const m=document.getElementById('modal');document.getElementById('formId').value='';document.getElementById('nombre').value='';document.getElementById('apellido').value='';document.getElementById('telefono').value='';document.getElementById('email').value='';document.getElementById('notas').value='';if(tipo==='crear'){document.getElementById('modalTitle').textContent='Nuevo Cliente';document.getElementById('formAccion').value='crear';}else{document.getElementById('modalTitle').textContent='Editar Cliente';document.getElementById('formAccion').value='editar';document.getElementById('formId').value=data.id;document.getElementById('nombre').value=data.nombre||'';document.getElementById('apellido').value=data.apellido||'';document.getElementById('telefono').value=data.telefono||'';document.getElementById('email').value=data.email||'';document.getElementById('notas').value=data.notas||'';}m.classList.remove('hidden');}
function closeModal(){document.getElementById('modal').classList.add('hidden');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
</script>

<?php require_once '../includes/footer.php'; ?>