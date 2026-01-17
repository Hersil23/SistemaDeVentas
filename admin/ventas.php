<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

requireAdmin();

$mensaje = '';
$error = '';

// Obtener configuración de WhatsApp
$configResult = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('mensaje_entrega', 'mensaje_cambio', 'mensaje_cobro', 'mensaje_renovar', 'moneda_principal', 'tasa_cambio', 'banco', 'telefono_pago', 'cuenta_banco')");
$config = [];
while ($row = $configResult->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad. Recargue la pagina.';
    } else {
        $accion = $_POST['accion'] ?? '';
        
        if ($accion === 'crear') {
            $cliente_id = sanitizeInt($_POST['cliente_id'] ?? 0);
            $perfil_id = sanitizeInt($_POST['perfil_id'] ?? 0);
            $fecha_venta = $_POST['fecha_venta'] ?? date('Y-m-d');
            
            if ($cliente_id <= 0 || $perfil_id <= 0) {
                $error = 'Seleccione cliente y perfil';
            } else {
                // Verificar que el perfil está disponible
                $stmtCheck = $conn->prepare("SELECT p.id, p.cuenta_id, c.precio_venta, c.costo_compra, c.total_perfiles 
                                             FROM perfiles p 
                                             INNER JOIN cuentas c ON p.cuenta_id = c.id 
                                             WHERE p.id = ? AND p.estado = 'disponible'");
                $stmtCheck->bind_param("i", $perfil_id);
                $stmtCheck->execute();
                $perfilData = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();
                
                if (!$perfilData) {
                    $error = 'El perfil no esta disponible';
                } else {
                    // Calcular precios
                    $precio_venta = $perfilData['precio_venta'] ?? 0;
                    $costo_compra = $perfilData['costo_compra'] ?? 0;
                    $total_perfiles = $perfilData['total_perfiles'] ?? 1;
                    $precio_compra = $total_perfiles > 0 ? ($costo_compra / $total_perfiles) : 0;
                    
                    $conn->begin_transaction();
                    try {
                        // Crear venta
                        $vendedor_id = $_SESSION['user_id'] ?? 1;
                        $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, perfil_id, vendedor_id, precio_compra, precio_venta, fecha_venta) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiidds", $cliente_id, $perfil_id, $vendedor_id, $precio_compra, $precio_venta, $fecha_venta);
                        $stmt->execute();
                        $venta_id = $conn->insert_id;
                        $stmt->close();
                        
                        // Registrar pago inicial
                        $stmt = $conn->prepare("INSERT INTO pagos (venta_id, monto, fecha_pago, tipo) VALUES (?, ?, ?, 'inicial')");
                        $stmt->bind_param("ids", $venta_id, $precio_venta, $fecha_venta);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Marcar perfil como vendido
                        $stmt = $conn->prepare("UPDATE perfiles SET estado = 'vendido' WHERE id = ?");
                        $stmt->bind_param("i", $perfil_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $mensaje = 'Venta registrada correctamente';
                        logActivity('venta_creada', 'Perfil ID: ' . $perfil_id);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al registrar la venta';
                    }
                }
            }
        }
        
        if ($accion === 'anular') {
            $id = sanitizeInt($_POST['id'] ?? 0);
            
            // Obtener perfil_id de la venta
            $stmtVenta = $conn->prepare("SELECT perfil_id FROM ventas WHERE id = ?");
            $stmtVenta->bind_param("i", $id);
            $stmtVenta->execute();
            $ventaData = $stmtVenta->get_result()->fetch_assoc();
            $stmtVenta->close();
            
            if ($ventaData) {
                $conn->begin_transaction();
                try {
                    // Eliminar pagos asociados
                    $stmt = $conn->prepare("DELETE FROM pagos WHERE venta_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Eliminar venta
                    $stmt = $conn->prepare("DELETE FROM ventas WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Liberar perfil
                    $stmt = $conn->prepare("UPDATE perfiles SET estado = 'disponible' WHERE id = ?");
                    $stmt->bind_param("i", $ventaData['perfil_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $mensaje = 'Venta anulada y perfil liberado';
                    logActivity('venta_anulada', 'Venta ID: ' . $id);
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Error al anular la venta';
                }
            }
        }
        
        if ($accion === 'renovar') {
            $venta_id = sanitizeInt($_POST['venta_id'] ?? 0);
            $nueva_fecha = $_POST['nueva_fecha'] ?? '';
            
            if ($venta_id <= 0 || empty($nueva_fecha)) {
                $error = 'Datos incompletos para renovar';
            } else {
                // Obtener datos de la venta
                $stmtVenta = $conn->prepare("
                    SELECT v.precio_venta, p.cuenta_id 
                    FROM ventas v 
                    INNER JOIN perfiles p ON v.perfil_id = p.id 
                    WHERE v.id = ?
                ");
                $stmtVenta->bind_param("i", $venta_id);
                $stmtVenta->execute();
                $ventaData = $stmtVenta->get_result()->fetch_assoc();
                $stmtVenta->close();
                
                if ($ventaData) {
                    $conn->begin_transaction();
                    try {
                        // 1. Registrar pago de renovación
                        $fecha_hoy = date('Y-m-d');
                        $stmt = $conn->prepare("INSERT INTO pagos (venta_id, monto, fecha_pago, tipo) VALUES (?, ?, ?, 'renovacion')");
                        $stmt->bind_param("ids", $venta_id, $ventaData['precio_venta'], $fecha_hoy);
                        $stmt->execute();
                        $stmt->close();
                        
                        // 2. Actualizar fecha de vencimiento de la cuenta
                        $stmt = $conn->prepare("UPDATE cuentas SET fecha_vencimiento = ?, estado = 'activa' WHERE id = ?");
                        $stmt->bind_param("si", $nueva_fecha, $ventaData['cuenta_id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $mensaje = 'Renovacion registrada! Nueva fecha: ' . date('d/m/Y', strtotime($nueva_fecha));
                        logActivity('renovacion', 'Venta ID: ' . $venta_id . ' - Nueva fecha: ' . $nueva_fecha);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al renovar: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Venta no encontrada';
                }
            }
        }
        
        if ($accion === 'renovar_grupal') {
            $cliente_id = sanitizeInt($_POST['cliente_id'] ?? 0);
            $nueva_fecha = $_POST['nueva_fecha'] ?? '';
            $venta_ids = $_POST['venta_ids'] ?? '';
            
            if ($cliente_id <= 0 || empty($nueva_fecha) || empty($venta_ids)) {
                $error = 'Datos incompletos para renovar';
            } else {
                $ids = array_map('intval', explode(',', $venta_ids));
                
                // Calcular monto total
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                
                $stmt = $conn->prepare("SELECT SUM(precio_venta) as total FROM ventas WHERE id IN ($placeholders) AND cliente_id = ?");
                $params = array_merge($ids, [$cliente_id]);
                $stmt->bind_param($types . 'i', ...$params);
                $stmt->execute();
                $montoTotal = $stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();
                
                if ($montoTotal > 0) {
                    $conn->begin_transaction();
                    try {
                        $fecha_hoy = date('Y-m-d');
                        
                        // Registrar UN solo pago con el monto total (usando la primera venta como referencia)
                        $stmt = $conn->prepare("INSERT INTO pagos (venta_id, monto, fecha_pago, tipo) VALUES (?, ?, ?, 'renovacion')");
                        $stmt->bind_param("ids", $ids[0], $montoTotal, $fecha_hoy);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Actualizar fecha de vencimiento de TODAS las cuentas de esas ventas
                        $stmt = $conn->prepare("
                            UPDATE cuentas c
                            INNER JOIN perfiles p ON p.cuenta_id = c.id
                            INNER JOIN ventas v ON v.perfil_id = p.id
                            SET c.fecha_vencimiento = ?, c.estado = 'activa'
                            WHERE v.id IN ($placeholders) AND v.cliente_id = ?
                        ");
                        $params = array_merge([$nueva_fecha], $ids, [$cliente_id]);
                        $stmt->bind_param('s' . $types . 'i', ...$params);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        $mensaje = 'Renovacion grupal registrada! ' . count($ids) . ' servicios renovados hasta ' . date('d/m/Y', strtotime($nueva_fecha));
                        logActivity('renovacion_grupal', 'Cliente ID: ' . $cliente_id . ' - Ventas: ' . $venta_ids);
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = 'Error al renovar: ' . $e->getMessage();
                    }
                } else {
                    $error = 'No se encontraron ventas para renovar';
                }
            }
        }
    }
}

// Filtros
$filtro_servicio = sanitizeInt($_GET['servicio'] ?? 0);
$filtro_cliente = sanitizeInt($_GET['cliente'] ?? 0);
$filtro_fecha = sanitizeInput($_GET['fecha'] ?? '');
$busqueda = sanitizeInput($_GET['q'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if ($filtro_servicio > 0) { $where .= " AND s.id = ?"; $params[] = $filtro_servicio; $types .= "i"; }
if ($filtro_cliente > 0) { $where .= " AND v.cliente_id = ?"; $params[] = $filtro_cliente; $types .= "i"; }
if (!empty($filtro_fecha)) { $where .= " AND DATE(v.fecha_venta) = ?"; $params[] = $filtro_fecha; $types .= "s"; }
if (!empty($busqueda)) { 
    $where .= " AND (cl.nombre LIKE ? OR cl.telefono LIKE ? OR s.nombre LIKE ? OR c.cuenta LIKE ?)"; 
    $busquedaLike = "%$busqueda%";
    $params[] = $busquedaLike; $types .= "s";
    $params[] = $busquedaLike; $types .= "s";
    $params[] = $busquedaLike; $types .= "s";
    $params[] = $busquedaLike; $types .= "s";
}

// Obtener ventas con todos los datos necesarios para WhatsApp
$sql = "SELECT v.*, 
        cl.nombre as cliente, cl.telefono as cliente_telefono,
        p.numero_perfil, p.pin, p.cuenta_id,
        c.cuenta, c.password, c.fecha_compra, c.fecha_vencimiento, c.precio_venta as precio_perfil,
        s.nombre as servicio
        FROM ventas v
        INNER JOIN clientes cl ON v.cliente_id = cl.id
        INNER JOIN perfiles p ON v.perfil_id = p.id
        INNER JOIN cuentas c ON p.cuenta_id = c.id
        INNER JOIN servicios s ON c.servicio_id = s.id
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

// Agrupar ventas por cliente para detectar cobro grupal
$ventasPorCliente = [];
$clientesYaMostrados = []; // Para controlar que el botón grupal solo aparezca una vez
foreach ($ventas as $v) {
    $clienteId = $v['cliente_id'];
    if (!isset($ventasPorCliente[$clienteId])) {
        $ventasPorCliente[$clienteId] = [];
    }
    $ventasPorCliente[$clienteId][] = $v;
}

// Obtener datos para formulario
$clientes = $conn->query("SELECT * FROM clientes WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$servicios = $conn->query("SELECT * FROM servicios WHERE estado = 'activo' ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$todosServicios = $conn->query("SELECT * FROM servicios ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
$todosClientes = $conn->query("SELECT * FROM clientes ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

// Obtener cuentas con perfiles disponibles para el selector
$cuentasConPerfiles = $conn->query("
    SELECT c.id, c.cuenta, c.password, c.precio_venta, s.nombre as servicio,
           (SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND estado = 'disponible') as disponibles
    FROM cuentas c
    INNER JOIN servicios s ON c.servicio_id = s.id
    WHERE c.estado = 'activa'
    HAVING disponibles > 0
    ORDER BY s.nombre, c.cuenta
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Ventas';
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
                <button onclick="openModal()" class="flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span class="hidden sm:inline">Nueva Venta</span></button>
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"><svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg><svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
            </div>
        </header>
        
        <main class="p-4 lg:p-6">
            <?php if (!empty($mensaje)): ?><div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800"><p class="text-sm text-green-600 dark:text-green-400"><?php echo e($mensaje); ?></p></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"><p class="text-sm text-red-600 dark:text-red-400"><?php echo e($error); ?></p></div><?php endif; ?>
            
            <!-- Buscador y Filtros -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border p-4 mb-4">
                <form method="GET" class="space-y-3">
                    <!-- Buscador -->
                    <div class="flex gap-2">
                        <div class="flex-1 relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" name="q" value="<?php echo e($busqueda); ?>" placeholder="Buscar cliente, telefono, servicio..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white" id="inputBusqueda">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg hover:bg-slate-300 dark:hover:bg-slate-600" title="Buscar"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></button>
                    </div>
                    
                    <!-- Filtros avanzados -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <select name="servicio" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            <option value="">Todos los servicios</option>
                            <?php foreach ($todosServicios as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo $filtro_servicio == $s['id'] ? 'selected' : ''; ?>><?php echo e($s['nombre']); ?></option><?php endforeach; ?>
                        </select>
                        <select name="cliente" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                            <option value="">Todos los clientes</option>
                            <?php foreach ($todosClientes as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $filtro_cliente == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['nombre']); ?></option><?php endforeach; ?>
                        </select>
                        <input type="date" name="fecha" value="<?php echo e($filtro_fecha); ?>" class="flex-1 px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg">Filtrar</button>
                        <?php if ($filtro_servicio || $filtro_cliente || $filtro_fecha || $busqueda): ?><a href="ventas.php" class="px-4 py-2 text-slate-500 text-center">Limpiar</a><?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Ventas -->
            <div class="bg-light-card dark:bg-dark-card rounded-xl border border-light-border dark:border-dark-border overflow-hidden">
                <div class="p-4 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-800 dark:text-white">Historial de Ventas</h2>
                        <p class="text-sm text-slate-500"><?php echo count($ventas); ?> ventas</p>
                    </div>
                </div>
                
                <?php if (empty($ventas)): ?>
                <div class="p-8 text-center"><p class="text-slate-500">No hay ventas registradas</p><button onclick="openModal()" class="mt-4 px-4 py-2 bg-primary-500 text-white rounded-lg">Registrar primera venta</button></div>
                <?php else: ?>
                
                <!-- Tabla Desktop -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-800/50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cliente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Servicio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cuenta / Perfil</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Precio</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Vence</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">WhatsApp</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-light-border dark:divide-dark-border">
                        <?php foreach ($ventas as $v): 
                            $diasVence = $v['fecha_vencimiento'] ? (strtotime($v['fecha_vencimiento']) - time()) / 86400 : null;
                            $claseVence = '';
                            $vencido = false;
                            if ($diasVence !== null) {
                                if ($diasVence < 0) { $claseVence = 'text-red-600 dark:text-red-400 font-bold'; $vencido = true; }
                                elseif ($diasVence <= 7) $claseVence = 'text-amber-600 dark:text-amber-400';
                            }
                            $tieneMultiples = count($ventasPorCliente[$v['cliente_id']] ?? []) > 1;
                            $mostrarBotonGrupal = $tieneMultiples && !in_array($v['cliente_id'], $clientesYaMostrados);
                            if ($mostrarBotonGrupal) $clientesYaMostrados[] = $v['cliente_id'];
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente']); ?></span>
                                <?php if ($v['cliente_telefono']): ?><p class="text-xs text-slate-400"><?php echo e($v['cliente_telefono']); ?></p><?php endif; ?>
                                <?php if ($tieneMultiples): ?><span class="inline-flex mt-1 px-1.5 py-0.5 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded"><?php echo count($ventasPorCliente[$v['cliente_id']]); ?> servicios</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><?php echo e($v['servicio']); ?></td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-slate-600 dark:text-slate-300"><?php echo e($v['cuenta']); ?></span>
                                <p class="text-xs text-slate-400">Perfil <?php echo $v['numero_perfil']; ?><?php echo $v['pin'] ? ' | PIN: '.$v['pin'] : ''; ?></p>
                            </td>
                            <td class="px-4 py-3 text-center font-medium text-slate-800 dark:text-white">$<?php echo number_format($v['precio_venta'], 2); ?></td>
                            <td class="px-4 py-3 text-center text-sm <?php echo $claseVence; ?>"><?php echo $v['fecha_vencimiento'] ? date('d/m/Y', strtotime($v['fecha_vencimiento'])) : '-'; ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="enviarWhatsApp('entrega', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg" title="Entrega"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button>
                                    <button onclick="enviarWhatsApp('cambio', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg" title="Cambio"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
                                    <button onclick="enviarWhatsApp('cobro', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg" title="Cobro"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                                    <?php if ($mostrarBotonGrupal): ?>
                                    <button onclick="cobroGrupal(<?php echo $v['cliente_id']; ?>)" class="p-2 text-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg" title="Cobro Grupal"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></button>
                                    <button onclick="abrirModalRenovarGrupal(<?php echo $v['cliente_id']; ?>)" class="p-2 text-pink-500 hover:bg-pink-50 dark:hover:bg-pink-900/20 rounded-lg" title="Renovar Todos"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
                                    <?php endif; ?>
                                    <button onclick="abrirModalRenovar(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-teal-500 hover:bg-teal-50 dark:hover:bg-teal-900/20 rounded-lg" title="Renovar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="verDetalles(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="p-2 text-slate-500 hover:text-blue-500 rounded-lg" title="Ver detalles"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>
                                    <form method="POST" class="inline" onsubmit="return confirm('¿Anular esta venta?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="accion" value="anular">
                                        <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                        <button type="submit" class="p-2 text-slate-500 hover:text-red-500 rounded-lg" title="Anular"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Cards Mobile -->
                <?php $clientesYaMostradosMobile = []; ?>
                <div class="lg:hidden divide-y divide-light-border dark:divide-dark-border">
                    <?php foreach ($ventas as $v): 
                        $diasVence = $v['fecha_vencimiento'] ? (strtotime($v['fecha_vencimiento']) - time()) / 86400 : null;
                        $claseVence = '';
                        if ($diasVence !== null) {
                            if ($diasVence < 0) $claseVence = 'text-red-600 font-bold';
                            elseif ($diasVence <= 7) $claseVence = 'text-amber-600';
                        }
                        $tieneMultiples = count($ventasPorCliente[$v['cliente_id']] ?? []) > 1;
                        $mostrarBotonGrupalMobile = $tieneMultiples && !in_array($v['cliente_id'], $clientesYaMostradosMobile);
                        if ($mostrarBotonGrupalMobile) $clientesYaMostradosMobile[] = $v['cliente_id'];
                    ?>
                    <div class="p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-medium text-slate-800 dark:text-white"><?php echo e($v['cliente']); ?></h3>
                                <p class="text-sm text-slate-500"><?php echo e($v['servicio']); ?></p>
                                <?php if ($tieneMultiples): ?><span class="inline-flex mt-1 px-1.5 py-0.5 text-xs bg-blue-100 text-blue-600 rounded"><?php echo count($ventasPorCliente[$v['cliente_id']]); ?> servicios</span><?php endif; ?>
                            </div>
                            <span class="text-sm font-medium text-slate-800 dark:text-white">$<?php echo number_format($v['precio_venta'], 2); ?></span>
                        </div>
                        <p class="text-xs text-slate-400 mb-2"><?php echo e($v['cuenta']); ?> | Perfil <?php echo $v['numero_perfil']; ?></p>
                        <p class="text-sm <?php echo $claseVence; ?> mb-3">Vence: <?php echo $v['fecha_vencimiento'] ? date('d/m/Y', strtotime($v['fecha_vencimiento'])) : 'N/A'; ?></p>
                        
                        <!-- Botones WhatsApp Mobile -->
                        <div class="grid grid-cols-4 gap-2 mb-2">
                            <button onclick="enviarWhatsApp('entrega', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="px-2 py-2 text-xs text-green-600 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">Entrega</button>
                            <button onclick="enviarWhatsApp('cambio', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="px-2 py-2 text-xs text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center">Cambio</button>
                            <button onclick="enviarWhatsApp('cobro', <?php echo htmlspecialchars(json_encode($v)); ?>)" class="px-2 py-2 text-xs text-blue-600 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">Cobro</button>
                            <button onclick="abrirModalRenovar(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="px-2 py-2 text-xs text-teal-600 bg-teal-50 dark:bg-teal-900/20 rounded-lg text-center">Renovar</button>
                        </div>
                        <?php if ($mostrarBotonGrupalMobile): ?>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <button onclick="cobroGrupal(<?php echo $v['cliente_id']; ?>)" class="px-2 py-2 text-xs text-purple-600 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">Cobro Grupal (<?php echo count($ventasPorCliente[$v['cliente_id']]); ?>)</button>
                            <button onclick="abrirModalRenovarGrupal(<?php echo $v['cliente_id']; ?>)" class="px-2 py-2 text-xs text-pink-600 bg-pink-50 dark:bg-pink-900/20 rounded-lg text-center">Renovar Todos</button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-2">
                            <button onclick="verDetalles(<?php echo htmlspecialchars(json_encode($v)); ?>)" class="flex-1 px-3 py-2 text-sm text-blue-600 bg-blue-50 rounded-lg">Detalles</button>
                            <form method="POST" class="flex-1" onsubmit="return confirm('¿Anular esta venta?')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="accion" value="anular">
                                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                <button type="submit" class="w-full px-3 py-2 text-sm text-red-600 bg-red-50 rounded-lg">Anular</button>
                            </form>
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
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
            <h3 class="font-semibold text-slate-800 dark:text-white">Nueva Venta</h3>
            <button onclick="closeModal()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" class="p-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="crear">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cliente *</label>
                    <select name="cliente_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Seleccionar cliente...</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['nombre']); ?><?php echo $c['telefono'] ? ' - '.$c['telefono'] : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cuenta *</label>
                    <select name="cuenta_id" id="cuenta_id" required onchange="cargarPerfiles()" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Seleccionar cuenta...</option>
                        <?php foreach ($cuentasConPerfiles as $c): ?>
                        <option value="<?php echo $c['id']; ?>" data-precio="<?php echo $c['precio_venta']; ?>"><?php echo e($c['servicio']); ?> - <?php echo e($c['cuenta']); ?> (<?php echo $c['disponibles']; ?> disp.) - $<?php echo number_format($c['precio_venta'], 2); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Perfil *</label>
                    <select name="perfil_id" id="perfil_id" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                        <option value="">Primero seleccione una cuenta...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha de venta</label>
                    <input type="date" name="fecha_venta" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-primary-500 text-white rounded-lg">Registrar Venta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Renovar -->
<div id="modalRenovar" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModalRenovar()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
            <h3 class="font-semibold text-slate-800 dark:text-white">Renovar Suscripcion</h3>
            <button onclick="cerrarModalRenovar()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" class="p-4" id="formRenovar">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="renovar">
            <input type="hidden" name="venta_id" id="renovar_venta_id">
            
            <div class="space-y-4">
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                    <p class="text-sm text-slate-600 dark:text-slate-300"><strong>Cliente:</strong> <span id="renovar_cliente"></span></p>
                    <p class="text-sm text-slate-600 dark:text-slate-300"><strong>Servicio:</strong> <span id="renovar_servicio"></span></p>
                    <p class="text-sm text-slate-600 dark:text-slate-300"><strong>Precio:</strong> <span id="renovar_precio" class="text-green-600 font-bold"></span></p>
                    <p class="text-sm text-slate-600 dark:text-slate-300"><strong>Vence:</strong> <span id="renovar_vence_actual" class="text-red-600"></span></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nueva fecha de vencimiento *</label>
                    <input type="date" name="nueva_fecha" id="renovar_nueva_fecha" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                </div>
                
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="enviar_whatsapp_renovar" checked class="rounded border-slate-300">
                    <label for="enviar_whatsapp_renovar" class="text-sm text-slate-600 dark:text-slate-300">Enviar mensaje de confirmacion por WhatsApp</label>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="cerrarModalRenovar()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-teal-500 text-white rounded-lg">Renovar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalles -->
<div id="modalDetalles" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="closeModalDetalles()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-md bg-light-card dark:bg-dark-card rounded-xl shadow-xl">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
            <h3 class="font-semibold text-slate-800 dark:text-white">Detalles de la Venta</h3>
            <button onclick="closeModalDetalles()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <div id="detallesContent" class="p-4"></div>
    </div>
</div>

<!-- Modal Renovar Grupal -->
<div id="modalRenovarGrupal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" onclick="cerrarModalRenovarGrupal()"></div>
    <div class="absolute inset-4 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:w-full sm:max-w-lg bg-light-card dark:bg-dark-card rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-light-border dark:border-dark-border">
            <h3 class="font-semibold text-slate-800 dark:text-white">Renovar Todos los Servicios</h3>
            <button onclick="cerrarModalRenovarGrupal()" class="p-2 text-slate-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" class="p-4" id="formRenovarGrupal">
            <?php echo csrfField(); ?>
            <input type="hidden" name="accion" value="renovar_grupal">
            <input type="hidden" name="cliente_id" id="renovar_grupal_cliente_id">
            <input type="hidden" name="venta_ids" id="renovar_grupal_venta_ids">
            
            <div class="space-y-4">
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Cliente: <span id="renovar_grupal_cliente" class="font-bold"></span></p>
                        <button type="button" onclick="toggleTodosCheckbox()" class="text-xs text-primary-600 hover:underline">Seleccionar/Deseleccionar todos</button>
                    </div>
                    <div id="renovar_grupal_servicios" class="space-y-1 max-h-48 overflow-y-auto"></div>
                    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <p class="text-lg font-bold text-green-600">Total: <span id="renovar_grupal_total"></span></p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nueva fecha de vencimiento para todos *</label>
                    <input type="date" name="nueva_fecha" id="renovar_grupal_fecha" required class="w-full px-4 py-2 rounded-lg border border-light-border dark:border-dark-border bg-white dark:bg-slate-800 text-slate-800 dark:text-white">
                </div>
                
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="enviar_whatsapp_grupal" checked class="rounded border-slate-300">
                    <label for="enviar_whatsapp_grupal" class="text-sm text-slate-600 dark:text-slate-300">Enviar mensaje de confirmacion por WhatsApp</label>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="cerrarModalRenovarGrupal()" class="flex-1 px-4 py-2 border border-light-border dark:border-dark-border text-slate-600 dark:text-slate-300 rounded-lg">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-pink-500 text-white rounded-lg">Renovar Todos</button>
            </div>
        </form>
    </div>
</div>

<script>
// Datos para cobro grupal
const ventasPorCliente = <?php echo json_encode($ventasPorCliente); ?>;

// Mensajes de WhatsApp desde configuración
const mensajesWhatsApp = {
    entrega: <?php echo json_encode($config['mensaje_entrega'] ?? ''); ?>,
    cambio: <?php echo json_encode($config['mensaje_cambio'] ?? ''); ?>,
    cobro: <?php echo json_encode($config['mensaje_cobro'] ?? ''); ?>,
    renovar: <?php echo json_encode($config['mensaje_renovar'] ?? ''); ?>
};
const configPago = {
    moneda: <?php echo json_encode($config['moneda_principal'] ?? 'USD'); ?>,
    tasa: parseFloat(<?php echo json_encode($config['tasa_cambio'] ?? '1'); ?>) || 1,
    banco: <?php echo json_encode($config['banco'] ?? ''); ?>,
    telefono_pago: <?php echo json_encode($config['telefono_pago'] ?? ''); ?>,
    cuenta_banco: <?php echo json_encode($config['cuenta_banco'] ?? ''); ?>
};

// Función para convertir precio según moneda
function convertirPrecio(precioUSD) {
    if (configPago.moneda === 'BS') {
        return (precioUSD * configPago.tasa).toFixed(2);
    }
    return precioUSD.toFixed(2);
}

function formatearPrecio(precioUSD) {
    const simbolo = configPago.moneda === 'USD' ? '$' : 'Bs ';
    return simbolo + convertirPrecio(precioUSD);
}

let ventaActualRenovar = null;
let datosRenovarGrupal = null;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}

function openModal() {
    document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}

function cargarPerfiles() {
    const cuentaId = document.getElementById('cuenta_id').value;
    const perfilSelect = document.getElementById('perfil_id');
    
    if (!cuentaId) {
        perfilSelect.innerHTML = '<option value="">Primero seleccione una cuenta...</option>';
        return;
    }
    
    perfilSelect.innerHTML = '<option value="">Cargando...</option>';
    
    fetch('ajax/get_perfiles.php?cuenta_id=' + cuentaId + '&disponibles=1')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.perfiles.length > 0) {
                let html = '<option value="">Seleccionar perfil...</option>';
                data.perfiles.forEach(p => {
                    html += `<option value="${p.id}">Perfil ${p.numero_perfil}${p.pin ? ' (PIN: '+p.pin+')' : ''}</option>`;
                });
                perfilSelect.innerHTML = html;
            } else {
                perfilSelect.innerHTML = '<option value="">No hay perfiles disponibles</option>';
            }
        })
        .catch(() => {
            perfilSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
}

function enviarWhatsApp(tipo, venta, nuevaFecha = null) {
    let telefono = venta.cliente_telefono || '';
    telefono = telefono.replace(/[^0-9]/g, '');
    
    if (!telefono) {
        alert('El cliente no tiene telefono registrado');
        return;
    }
    
    if (telefono.length === 10 || telefono.length === 11) {
        telefono = '58' + telefono;
    }
    
    let mensaje = mensajesWhatsApp[tipo] || '';
    
    if (!mensaje) {
        alert('No hay mensaje configurado. Configure en Configuracion.');
        return;
    }
    
    const fechaVencimiento = nuevaFecha || venta.fecha_vencimiento;
    const precioUSD = parseFloat(venta.precio_venta || 0);
    const precioConvertido = convertirPrecio(precioUSD);
    const simbolo = configPago.moneda === 'USD' ? '$' : 'Bs ';
    
    mensaje = mensaje
        .replace(/{cliente}/g, venta.cliente || '')
        .replace(/{servicio}/g, venta.servicio || '')
        .replace(/{cuenta}/g, venta.cuenta || '')
        .replace(/{password}/g, venta.password || '')
        .replace(/{perfil}/g, venta.numero_perfil || '')
        .replace(/{pin}/g, venta.pin || 'N/A')
        .replace(/{fecha_compra}/g, venta.fecha_compra ? formatDate(venta.fecha_compra) : '')
        .replace(/{vencimiento}/g, fechaVencimiento ? formatDate(fechaVencimiento) : 'N/A')
        .replace(/{precio_usd}/g, '$' + precioUSD.toFixed(2))
        .replace(/{precio_bs}/g, 'Bs ' + (precioUSD * configPago.tasa).toFixed(2))
        .replace(/{precio}/g, simbolo + precioConvertido)
        .replace(/{banco}/g, configPago.banco || '')
        .replace(/{telefono_pago}/g, configPago.telefono_pago || '')
        .replace(/{cuenta_banco}/g, configPago.cuenta_banco || '');
    
    const url = 'https://wa.me/' + telefono + '?text=' + encodeURIComponent(mensaje);
    window.open(url, '_blank');
}

function cobroGrupal(clienteId) {
    const ventas = ventasPorCliente[clienteId];
    if (!ventas || ventas.length === 0) return;
    
    let telefono = ventas[0].cliente_telefono || '';
    telefono = telefono.replace(/[^0-9]/g, '');
    
    if (!telefono) {
        alert('El cliente no tiene telefono registrado');
        return;
    }
    
    if (telefono.length === 10 || telefono.length === 11) {
        telefono = '58' + telefono;
    }
    
    let totalUSD = 0;
    let detalleServicios = '';
    const simbolo = configPago.moneda === 'USD' ? '$' : 'Bs ';
    
    ventas.forEach(v => {
        const precioUSD = parseFloat(v.precio_venta) || 0;
        totalUSD += precioUSD;
        const vence = v.fecha_vencimiento ? formatDate(v.fecha_vencimiento) : 'N/A';
        detalleServicios += `*${v.servicio}*
Correo: ${v.cuenta || 'N/A'}
Perfil: ${v.numero_perfil}${v.pin ? ' | PIN: ' + v.pin : ''}
Vence: ${vence}
Precio: ${formatearPrecio(precioUSD)}

`;
    });
    
    let mensaje = `IMPORTANTE! NO ATENDEMOS EMERGENCIAS

Estimad(a) *${ventas[0].cliente}*
Le informamos que sus servicios requieren renovacion:

${detalleServicios}*TOTAL A PAGAR: ${formatearPrecio(totalUSD)}*

Para renovar, realice el pago a traves de:
*${configPago.banco}*
- Tlf: ${configPago.telefono_pago}
- Cuenta: ${configPago.cuenta_banco}

Gracias! Si no desea renovar alguno, por favor informenos.`;
    
    const url = 'https://wa.me/' + telefono + '?text=' + encodeURIComponent(mensaje);
    window.open(url, '_blank');
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function abrirModalRenovar(venta) {
    ventaActualRenovar = venta;
    document.getElementById('renovar_venta_id').value = venta.id;
    document.getElementById('renovar_cliente').textContent = venta.cliente;
    document.getElementById('renovar_servicio').textContent = venta.servicio + ' - Perfil ' + venta.numero_perfil;
    document.getElementById('renovar_precio').textContent = '$' + parseFloat(venta.precio_venta).toFixed(2);
    document.getElementById('renovar_vence_actual').textContent = venta.fecha_vencimiento ? formatDate(venta.fecha_vencimiento) : 'N/A';
    
    // Calcular nueva fecha (+1 mes desde hoy)
    const hoy = new Date();
    hoy.setMonth(hoy.getMonth() + 1);
    document.getElementById('renovar_nueva_fecha').value = hoy.toISOString().split('T')[0];
    
    document.getElementById('modalRenovar').classList.remove('hidden');
}

function cerrarModalRenovar() {
    document.getElementById('modalRenovar').classList.add('hidden');
    ventaActualRenovar = null;
}

// Interceptar submit del form de renovar para usar AJAX y luego abrir WhatsApp
document.getElementById('formRenovar').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const enviarWA = document.getElementById('enviar_whatsapp_renovar').checked;
    const nuevaFecha = document.getElementById('renovar_nueva_fecha').value;
    
    fetch('ventas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Verificar si fue exitoso buscando el mensaje de éxito
        if (html.includes('Renovacion registrada')) {
            // Enviar WhatsApp si está marcado
            if (enviarWA && ventaActualRenovar) {
                enviarWhatsApp('renovar', ventaActualRenovar, nuevaFecha);
            }
            // Recargar página para mostrar cambios
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Si hubo error, recargar para mostrar el mensaje
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.reload();
    });
});

function verDetalles(venta) {
    const utilidad = parseFloat(venta.precio_venta) - parseFloat(venta.precio_compra);
    const utilidadClass = utilidad >= 0 ? 'text-green-600' : 'text-red-600';
    
    document.getElementById('detallesContent').innerHTML = `
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Cliente</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.cliente}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Telefono</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.cliente_telefono || 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Servicio</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.servicio}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Cuenta</span>
                <span class="font-medium text-slate-800 dark:text-white text-sm">${venta.cuenta}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Contrasena</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.password || 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Perfil / PIN</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.numero_perfil} / ${venta.pin || 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Vencimiento</span>
                <span class="font-medium text-slate-800 dark:text-white">${venta.fecha_vencimiento ? formatDate(venta.fecha_vencimiento) : 'N/A'}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-light-border dark:border-dark-border">
                <span class="text-slate-500">Precio Venta</span>
                <span class="font-bold text-primary-600">$${parseFloat(venta.precio_venta).toFixed(2)}</span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-slate-500">Utilidad</span>
                <span class="font-bold ${utilidadClass}">$${utilidad.toFixed(2)}</span>
            </div>
        </div>
    `;
    document.getElementById('modalDetalles').classList.remove('hidden');
}

function abrirModalRenovarGrupal(clienteId) {
    const ventas = ventasPorCliente[clienteId];
    if (!ventas || ventas.length === 0) return;
    
    datosRenovarGrupal = ventas;
    
    document.getElementById('renovar_grupal_cliente_id').value = clienteId;
    document.getElementById('renovar_grupal_cliente').textContent = ventas[0].cliente;
    
    // Generar lista de servicios con checkboxes
    let html = '';
    
    ventas.forEach((v, index) => {
        const precio = parseFloat(v.precio_venta) || 0;
        const venceActual = v.fecha_vencimiento ? formatDate(v.fecha_vencimiento) : 'N/A';
        html += `<label class="flex items-start gap-3 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer">
            <input type="checkbox" class="mt-1 rounded border-slate-300 checkbox-renovar" data-id="${v.id}" data-precio="${precio}" checked onchange="actualizarTotalGrupal()">
            <div class="flex-1">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">${v.servicio} - Perfil ${v.numero_perfil}</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-white">$${precio.toFixed(2)}</span>
                </div>
                <p class="text-xs text-slate-400">Vence: ${venceActual}</p>
            </div>
        </label>`;
    });
    
    document.getElementById('renovar_grupal_servicios').innerHTML = html;
    actualizarTotalGrupal();
    
    // Fecha por defecto: +1 mes
    const hoy = new Date();
    hoy.setMonth(hoy.getMonth() + 1);
    document.getElementById('renovar_grupal_fecha').value = hoy.toISOString().split('T')[0];
    
    document.getElementById('modalRenovarGrupal').classList.remove('hidden');
}

function actualizarTotalGrupal() {
    const checkboxes = document.querySelectorAll('.checkbox-renovar:checked');
    let totalUSD = 0;
    let ids = [];
    
    checkboxes.forEach(cb => {
        totalUSD += parseFloat(cb.dataset.precio) || 0;
        ids.push(cb.dataset.id);
    });
    
    document.getElementById('renovar_grupal_total').textContent = formatearPrecio(totalUSD);
    document.getElementById('renovar_grupal_venta_ids').value = ids.join(',');
    
    // Deshabilitar botón si no hay selección
    const btnSubmit = document.querySelector('#formRenovarGrupal button[type="submit"]');
    if (ids.length === 0) {
        btnSubmit.disabled = true;
        btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function toggleTodosCheckbox() {
    const checkboxes = document.querySelectorAll('.checkbox-renovar');
    const checkedCount = document.querySelectorAll('.checkbox-renovar:checked').length;
    const nuevoEstado = checkedCount < checkboxes.length;
    
    checkboxes.forEach(cb => cb.checked = nuevoEstado);
    actualizarTotalGrupal();
}

function cerrarModalRenovarGrupal() {
    document.getElementById('modalRenovarGrupal').classList.add('hidden');
    datosRenovarGrupal = null;
}

function enviarWhatsAppRenovarGrupal(ventas, nuevaFecha) {
    if (!ventas || ventas.length === 0) return;
    
    // Obtener solo los IDs seleccionados
    const idsSeleccionados = document.getElementById('renovar_grupal_venta_ids').value.split(',');
    const ventasSeleccionadas = ventas.filter(v => idsSeleccionados.includes(v.id.toString()));
    
    if (ventasSeleccionadas.length === 0) return;
    
    let telefono = ventasSeleccionadas[0].cliente_telefono || '';
    telefono = telefono.replace(/[^0-9]/g, '');
    
    if (!telefono) {
        alert('El cliente no tiene telefono registrado');
        return;
    }
    
    if (telefono.length === 10 || telefono.length === 11) {
        telefono = '58' + telefono;
    }
    
    let totalUSD = 0;
    let detalleServicios = '';
    
    ventasSeleccionadas.forEach(v => {
        const precioUSD = parseFloat(v.precio_venta) || 0;
        totalUSD += precioUSD;
        detalleServicios += `*${v.servicio}*
Correo: ${v.cuenta || 'N/A'}
Contrasena: ${v.password || 'N/A'}
Perfil: ${v.numero_perfil}${v.pin ? ' | PIN: ' + v.pin : ''}
Vence: ${formatDate(nuevaFecha)}

`;
    });
    
    let mensaje = `Hola *${ventasSeleccionadas[0].cliente}*!
Su pago ha sido confirmado.

Servicios renovados:

${detalleServicios}*Total pagado:* ${formatearPrecio(totalUSD)}

Gracias por renovar con nosotros!`;
    
    const url = 'https://wa.me/' + telefono + '?text=' + encodeURIComponent(mensaje);
    window.open(url, '_blank');
}

// Interceptar submit del form de renovar grupal
document.getElementById('formRenovarGrupal').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const enviarWA = document.getElementById('enviar_whatsapp_grupal').checked;
    const nuevaFecha = document.getElementById('renovar_grupal_fecha').value;
    
    fetch('ventas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        if (html.includes('Renovacion grupal registrada')) {
            if (enviarWA && datosRenovarGrupal) {
                enviarWhatsAppRenovarGrupal(datosRenovarGrupal, nuevaFecha);
            }
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.reload();
    });
});

function closeModalDetalles() {
    document.getElementById('modalDetalles').classList.add('hidden');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal();
        closeModalDetalles();
        cerrarModalRenovar();
        cerrarModalRenovarGrupal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>