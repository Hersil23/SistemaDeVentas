<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

header('Content-Type: application/json');

$cuenta_id = isset($_GET['cuenta_id']) ? (int)$_GET['cuenta_id'] : 0;
$solo_disponibles = isset($_GET['disponibles']) ? true : false;

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
$result = $stmt->get_result();
$perfiles = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'perfiles' => $perfiles]);