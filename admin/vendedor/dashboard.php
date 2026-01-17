<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireVendedor();

$vendedor_id = getCurrentUserId();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina.';
    } else {
        $accion = $_POST['accion'] ?? '';
        
        // Crear cliente
        if ($accion === 'crear_cliente') {
            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $apellido = sanitizeInput($_POST['apellido'] ?? '');
            $telefono = sanitizeInput($_POST['telefono'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($nombre) || empty($telefono)) {
                $error = 'Nombre y telefono son obligatorios';
            } else {
                $stmt = $conn->prepare("INSERT INTO clientes (nombre, apellido, telefono, email, estado) VALUES (?, ?, ?, ?, 'activo')");
                $stmt->bind_param("ssss", $nombre, $apellido, $telefono, $email);
                if ($stmt->execute()) {
                    $mensaje = 'Cliente creado correctamente';
                    logActivity('cliente_creado_vendedor', 'Cliente: ' . $nombre);
                } else {
                    $error = 'Error al crear el cliente';
                }
                $stmt->close();
            }
        }
        
        // Crear venta
        if ($accion === 'crear_venta') {
            $cliente_id = sanitizeInt($_POST['cliente_id'] ?? 0);
            $perfil_id = sanitizeInt($_POST['perfil_id'] ?? 0);
            $duracion_meses = sanitizeInt($_POST['duracion_meses'] ?? 1);
            $precio_venta = sanitizeDecimal($_POST['precio_venta'] ?? 0);
            $notas = sanitizeInput($_POST['notas'] ?? '');
            
            if ($cliente_id <= 0 || $perfil_id <= 0) {
                $error = 'Seleccione cliente y perfil';
            } else if ($duracion_meses < 1 || $duracion_meses > 12) {
                $error = 'Duracion debe ser entre 1 y 12 meses';
            } else if ($precio_venta <= 0) {
                $error = 'Ingrese un precio valido';
            } else {
                // Verificar perfil disponible
                $stmt = $conn->prepare("SELECT id FROM perfiles WHERE id = ? AND estado = 'disponible'");
                $stmt->bind_param("i", $perfil_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $error = 'El perfil no esta disponible';
                } else {
                    $conn->begin_transaction();
                    try {
                        $fecha_venta = date('Y-m-d');
                        $fecha_vencimiento = date('Y-m-d', strtotime("+$duracion_meses months"));
                        
                        $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, perfil_id, vendedor_id, precio_venta, duracion_meses, fecha_venta, fecha_vencimiento, notas, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activa')");
                        $stmt->bind_param("iiidisss", $cliente_id, $perfil_id, $vendedor_id, $precio_venta, $duracion_meses, $fecha_venta, $fecha_vencimiento, $notas);
                        $stmt->execute();
                        $venta_id = $conn->insert_id;
                        
                        $stmt = $conn->prepare("UPDATE perfiles SET estado = 'vendido' WHERE id = ?");
                        $stmt->bind_param("i", $perfil_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        $mensaje = 'Venta registrada correctamente. ID: ' . $venta_id;
                        logActivity('venta_creada_vendedor', 'Venta ID: ' . $venta_id);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al registrar la venta';
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Estadísticas del vendedor (mes actual)
$mes_inicio = date('Y-m-01');
$mes_fin = date('Y-m-d');

$stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(precio_venta),0) as ingresos FROM ventas WHERE vendedor_id = ? AND fecha_venta BETWEEN ? AND ?");
$stmt->bind_param("iss", $vendedor_id, $mes_inicio, $mes_fin);
$stmt->execute();
$stats_mes = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas WHERE vendedor_id = ?");
$stmt->bind_param("i", $vendedor_id);
$stmt->execute();
$stats_total = $stmt->get_result()->fetch_assoc();

// Mis ventas recientes
$stmt = $conn->prepare("SELECT v.*, 
    CONCAT(cl.nombre,' ',cl.apellido) as cliente, cl.telefono as cliente_telefono,
    s.nombre as servicio, c.cuenta, c.password as cuenta_pass, p.numero_perfil
    FROM ventas v
    INNER JOIN clientes cl ON v.cliente_id = cl.id
    INNER JOIN perfiles p ON v.perfil_id = p.id
    INNER JOIN cuentas c ON p.cuenta_id = c.id
    INNER JOIN servicios s ON c.servicio_id = s.id
    WHERE v.vendedor_id = ?
    ORDER BY v.fecha_venta DESC, v.id DESC
    LIMIT 20");
$stmt->bind_param("i", $vendedor_id);
$stmt->execute();
$misVentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Datos para formularios
$clientes = $conn->query("SELECT * FROM clientes WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$servicios = $conn->query("SELECT * FROM servicios WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Panel Vendedor';
require_once '../includes/header.php';
?>

<div class="min-h-screen flex bg-light-bg dark:bg-dark-bg">
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-light-card dark:bg-dark-card border-r border-light-border dark:border-dark-border transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
        <div class="h-16 flex items-center gap-2 px-4 border-b border-light-border dark:border-dark-border">
            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></div>
            <span class="text-lg font-bold text-slate-800 dark:text-white">Vendedor</span>
        </div>
        <nav class="p-4 space-y-1 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-medium"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Mi Panel</a>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-light-border dark:border-dark-border">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600 dark:text-green-400 font-semibold"><?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?></div>
                <div class="flex-1 min-w-0"><p class="text-sm font-medium text-slate-800 dark:text-white truncate"><?php echo e(getCurrentUserName()); ?></p><p class="text-xs text-slate-500 dark:text-slate-400">Vendedor</p></div>
            </div>
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesion</a>
        </div>
    </aside>
    
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden" onclick="toggleSidebar()"></div>
    
    <div class="flex-1 lg:ml-64">
        <header class="h-16 bg-light-card dark:bg-dark-card border-b border-light-border dark:border-dark-border flex items-center justify-between px-4 lg:px-6">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <h1 class="text-lg font-semibold text-slate-800 dark:text-white">Mi Panel</h1>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openModalCliente()" class="flex items-center gap-2 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg><span class="hidden sm:inline">Cliente</span></button>
                <button onclick="openModalVenta()" class="flex items-center gap-2 px-3 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span class="hidden sm:inline">Venta</span></button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Ventas este mes</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white"><?php echo $stats_mes['total']; ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4">
                    <p class="text-xs text-slate-500 mb-1">Ingresos del mes</p>
                    <p class="text-2xl font-bold text-green-600">$<?php echo number_format($stats_mes['ingresos'], 2); ?></p>
                </div>
                <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4 col-span-2 lg:col-span-1">
                    <p class="text-xs text-slate-500 mb-1">Total historico</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $stats_total['total']; ?> ventas</p>
                </div>
            </div>
            
            <!-- Mis Ventas -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                <div class="p-4 border-b border-light-border dark:border-dark-border">
                    <h2 class="font-semibold text-slate-800 dark:text-white">Mis Ventas Recientes</h2>
                    <p class="text-sm text-slate-500"><?php echo count($misVentas); ?> ventas</p>
                </div>
                
                <?php if (empty($misVentas)): ?>
                <div class="p-8 text-center"><p class="text-slate-500">Aun no tienes ventas</p><button onclick="openModalVenta()" class="mt-4 px-4 py-2 bg-green-500 text-white rounded-lg">Registrar primera venta</button></div>
                <?php else: ?>
                
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-800/50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Servicio</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Precio</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Vence</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Estado</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($misVentas as $v): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo date('d/m/Y', strtotime($v['fecha_venta'])); ?></td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($v['servicio']); ?></td>
                            <td class="px-4 py-3 text-center font-medium text-green-600">$<?php echo number_format($v['precio_venta'], 2); ?></td>
                            <td class="px-4 py-3 text-center text-sm text-slate-600 dark:text-slate-300"><?php echo date('d/m/Y', strtotime($v['fecha_vencimiento'])); ?></td>
                            <td class="px-4 py-3 text-center"><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $v['estado']==='activa'?'bg-green-100 text-green-600':'bg-amber-100 text-amber-600'; ?>"><?php echo ucfirst($v['estado']); ?></span></td>
                            <td class="px-4 py-3 text-center">
                                <button onclick="verDetalle(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-slate-500 hover:text-blue-500" title="Ver"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                                <button onclick="enviarWhatsApp(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-slate-500 hover:text-green-500" title="WhatsApp"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="md:hidden divide-y divide-light-border dark:divide-dark-border">
                    <?php foreach ($misVentas as $v): ?>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div><h3 class="font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente']); ?></h3><p class="text-sm text-slate-500"><?php echo e($v['servicio']); ?></p></div>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php echo $v['estado']==='activa'?'bg-green-100 text-green-600':'bg-amber-100 text-amber-600'; ?>"><?php echo ucfirst($v['estado']); ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm mb-3">
                            <span class="text-green-600 font-medium">$<?php echo number_format($v['precio_venta'], 2); ?></span>
                            <span class="text-slate-500"><?php echo date('d/m/Y', strtotime($v['fecha_venta'])); ?></span>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="verDetalle(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="flex-1 px-3 py-2 text-sm text-blue-600 bg-blue-50 rounded-lg">Ver</button>
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

<!-- Modal Nuevo Cliente -->
<div id="modalCliente" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModalCliente()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 class="font-semibold text-slate-800 dark:text-white">Nuevo Cliente</h3><button onclick="closeModalCliente()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="crear_cliente">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre *</label><input type="text" name="nombre" required maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Apellido</label><input type="text" name="apellido" maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Telefono *</label><input type="text" name="telefono" required maxlength="20" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" placeholder="+584141234567"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label><input type="email" name="email" maxlength="100" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white"></div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeModalCliente()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button><button type="submit" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg">Guardar</button></div>
        </form>
    </div>
</div>

<!-- Modal Nueva Venta -->
<div id="modalVenta" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModalVenta()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border"><h3 class="font-semibold text-slate-800 dark:text-white">Nueva Venta</h3><button onclick="closeModalVenta()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="crear_venta">
            <div class="space-y-4">
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cliente *</label>
                    <select name="cliente_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($clientes as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo e($c['nombre'] . ' ' . $c['apellido'] . ($c['telefono'] ? ' - ' . $c['telefono'] : '')); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Servicio *</label>
                    <select id="servicio_v" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" onchange="cargarCuentas()">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($servicios as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo e($s['nombre']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cuenta *</label>
                    <select id="cuenta_v" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" onchange="cargarPerfiles()" disabled>
                        <option value="">Primero seleccione servicio</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Perfil *</label>
                    <select name="perfil_id" id="perfil_v" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" disabled>
                        <option value="">Primero seleccione cuenta</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Meses *</label>
                        <select name="duracion_meses" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Precio USD *</label>
                        <input type="number" name="precio_venta" step="0.01" min="0.01" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notas</label>
                    <textarea name="notas" rows="2" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6"><button type="button" onclick="closeModalVenta()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button><button type="submit" class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg">Registrar</button></div>
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
function openModalCliente(){document.getElementById('modalCliente').classList.remove('hidden');}
function closeModalCliente(){document.getElementById('modalCliente').classList.add('hidden');}
function openModalVenta(){document.getElementById('modalVenta').classList.remove('hidden');}
function closeModalVenta(){document.getElementById('modalVenta').classList.add('hidden');}
function closeModalDetalle(){document.getElementById('modalDetalle').classList.add('hidden');}

function cargarCuentas() {
    const sid = document.getElementById('servicio_v').value;
    const cs = document.getElementById('cuenta_v');
    const ps = document.getElementById('perfil_v');
    cs.innerHTML = '<option value="">Cargando...</option>'; cs.disabled = true;
    ps.innerHTML = '<option value="">Primero seleccione cuenta</option>'; ps.disabled = true;
    if (!sid) { cs.innerHTML = '<option value="">Primero seleccione servicio</option>'; return; }
    fetch('../admin/ajax/get_cuentas.php?servicio_id=' + sid).then(r => r.json()).then(d => {
        if (d.success && d.cuentas.length > 0) {
            cs.innerHTML = '<option value="">Seleccionar...</option>';
            d.cuentas.forEach(c => cs.innerHTML += `<option value="${c.id}">${c.cuenta} (${c.disponibles} disp.)</option>`);
            cs.disabled = false;
        } else { cs.innerHTML = '<option value="">No hay cuentas disponibles</option>'; }
    });
}

function cargarPerfiles() {
    const cid = document.getElementById('cuenta_v').value;
    const ps = document.getElementById('perfil_v');
    ps.innerHTML = '<option value="">Cargando...</option>'; ps.disabled = true;
    if (!cid) { ps.innerHTML = '<option value="">Primero seleccione cuenta</option>'; return; }
    fetch('../admin/ajax/get_perfiles.php?cuenta_id=' + cid + '&disponibles=1').then(r => r.json()).then(d => {
        if (d.success && d.perfiles.length > 0) {
            ps.innerHTML = '<option value="">Seleccionar...</option>';
            d.perfiles.forEach(p => ps.innerHTML += `<option value="${p.id}">Perfil ${p.numero_perfil}</option>`);
            ps.disabled = false;
        } else { ps.innerHTML = '<option value="">No hay perfiles disponibles</option>'; }
    });
}

function verDetalle(v) {
    const html = `
        <div class="space-y-3">
            <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Cliente</p><p class="font-medium text-slate-800 dark:text-white">${v.cliente}</p></div>
            <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Servicio</p><p class="font-medium text-slate-800 dark:text-white">${v.servicio}</p></div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg"><p class="text-xs text-blue-600 mb-1">Datos de Acceso</p><p class="font-medium text-slate-800 dark:text-white">${v.cuenta}</p><p class="text-sm text-slate-600">Pass: ${v.cuenta_pass || '-'}</p><p class="text-sm text-slate-600">Perfil: ${v.numero_perfil}</p></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Precio</p><p class="font-medium text-green-600">$${parseFloat(v.precio_venta).toFixed(2)}</p></div>
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Duracion</p><p class="font-medium">${v.duracion_meses} mes${v.duracion_meses>1?'es':''}</p></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Venta</p><p class="font-medium">${new Date(v.fecha_venta).toLocaleDateString('es')}</p></div>
                <div class="p-3 bg-light-bg dark:bg-dark-bg rounded-lg"><p class="text-xs text-slate-500">Vence</p><p class="font-medium">${new Date(v.fecha_vencimiento).toLocaleDateString('es')}</p></div>
            </div>
        </div>`;
    document.getElementById('detalleContent').innerHTML = html;
    document.getElementById('modalDetalle').classList.remove('hidden');
}

function enviarWhatsApp(v) {
    const tel = v.cliente_telefono ? v.cliente_telefono.replace(/[^0-9]/g, '') : '';
    if (!tel) { alert('Cliente sin telefono'); return; }
    const msg = `Hola ${v.cliente.split(' ')[0]}!\n\nDatos de tu cuenta de *${v.servicio}*:\n\nCorreo: ${v.cuenta}\nContrasena: ${v.cuenta_pass || '-'}\nPerfil: ${v.numero_perfil}\n\nVence: ${new Date(v.fecha_vencimiento).toLocaleDateString('es')}\n\nGracias!`;
    window.open(`https://wa.me/${tel}?text=${encodeURIComponent(msg)}`, '_blank');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModalCliente(); closeModalVenta(); closeModalDetalle(); } });
</script>

<?php require_once '../includes/footer.php'; ?>