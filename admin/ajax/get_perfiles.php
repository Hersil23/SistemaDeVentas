<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$cuenta_id = isset($_GET['cuenta_id']) ? (int)$_GET['cuenta_id'] : 0;
$solo_disponibles = isset($_GET['disponibles']) && $_GET['disponibles'] == '1';

if ($cuenta_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de cuenta invalido']);
    exit;
}

$sql = "SELECT id, numero_perfil, pin, estado FROM perfiles WHERE cuenta_id = ?";
if ($solo_disponibles) {
    $sql .= " AND estado = 'disponible'";
}
$sql .= " ORDER BY numero_perfil ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cuenta_id);
$stmt->execute();
$perfiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'perfiles' => $perfiles]);