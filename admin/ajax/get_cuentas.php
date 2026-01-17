<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$servicio_id = isset($_GET['servicio_id']) ? (int)$_GET['servicio_id'] : 0;

if ($servicio_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de servicio invalido']);
    exit;
}

// Obtener cuentas activas del servicio con al menos un perfil disponible
$stmt = $conn->prepare("
    SELECT c.id, c.cuenta, 
           (SELECT COUNT(*) FROM perfiles WHERE cuenta_id = c.id AND estado = 'disponible') as disponibles
    FROM cuentas c 
    WHERE c.servicio_id = ? 
      AND c.estado = 'activa'
    HAVING disponibles > 0
    ORDER BY c.cuenta ASC
");
$stmt->bind_param("i", $servicio_id);
$stmt->execute();
$cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'cuentas' => $cuentas]);