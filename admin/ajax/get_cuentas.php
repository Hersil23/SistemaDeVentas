<?php
/**
 * Obtener cuentas/servicios activos de un cliente
 * Archivo independiente sin includes problemáticos
 */
session_start();

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Conexión directa a base de datos
$host = 'localhost';
$dbname = 'sistema_ventas';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexion']);
    exit;
}

$clienteId = intval($_GET['cliente_id'] ?? 0);

if ($clienteId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de cliente invalido']);
    exit;
}

try {
    $sql = "SELECT 
                v.id as venta_id,
                v.precio_venta,
                DATE_FORMAT(v.fecha_venta, '%d/%m/%Y') as fecha_venta,
                s.nombre as servicio,
                c.cuenta,
                c.password,
                DATE_FORMAT(c.fecha_vencimiento, '%d/%m/%Y') as fecha_vencimiento,
                DATEDIFF(c.fecha_vencimiento, CURDATE()) as dias_vence,
                p.numero_perfil,
                p.pin
            FROM ventas v
            INNER JOIN perfiles p ON v.perfil_id = p.id
            INNER JOIN cuentas c ON p.cuenta_id = c.id
            INNER JOIN servicios s ON c.servicio_id = s.id
            WHERE v.cliente_id = ?
            ORDER BY s.nombre, p.numero_perfil";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cuentas = [];
    while ($row = $result->fetch_assoc()) {
        $cuentas[] = [
            'venta_id' => $row['venta_id'],
            'servicio' => $row['servicio'],
            'cuenta' => $row['cuenta'],
            'password' => $row['password'],
            'numero_perfil' => $row['numero_perfil'],
            'pin' => $row['pin'],
            'fecha_venta' => $row['fecha_venta'],
            'fecha_vencimiento' => $row['fecha_vencimiento'],
            'dias_vence' => intval($row['dias_vence']),
            'precio_venta' => $row['precio_venta']
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'cuentas' => $cuentas,
        'total' => count($cuentas)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al consultar']);
}