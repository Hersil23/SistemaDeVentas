<?php
// =============================================
// SISTEMA DE VENTAS - MÓDULO DE MONEDAS
// API: ve.dolarapi.com (BCV y Paralelo)
// =============================================

function crearTablaTasas() {
    global $conn;
    
    $conn->query("CREATE TABLE IF NOT EXISTS tasas_cambio (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fuente VARCHAR(50) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fuente (fuente),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $conn->query("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('moneda_fuente', 'paralelo', 'Fuente de tasa')");
    $conn->query("INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES ('mostrar_bs', '1', 'Mostrar Bolivares')");
}

function obtenerTasasAPI() {
    $url = 'https://ve.dolarapi.com/v1/dolares';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !is_array($data)) {
        return null;
    }
    
    return $data;
}

function obtenerTasa($fuente = 'paralelo') {
    global $conn;
    
    // Buscar tasa en caché (válida por 1 hora)
    $stmt = $conn->prepare("SELECT valor, updated_at FROM tasas_cambio WHERE fuente = ? AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $fuente);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return floatval($row['valor']);
    }
    $stmt->close();
    
    return actualizarTasa($fuente);
}

function actualizarTasa($fuente = 'paralelo') {
    global $conn;
    
    $tasas = obtenerTasasAPI();
    
    if (!$tasas) {
        $stmt = $conn->prepare("SELECT valor FROM tasas_cambio WHERE fuente = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("s", $fuente);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $valor = floatval($result->fetch_assoc()['valor']);
            $stmt->close();
            return $valor;
        }
        $stmt->close();
        return 0;
    }
    
    // Mapeo: bcv = oficial, paralelo = paralelo
    $mapeo = [
        'bcv' => 'oficial',
        'oficial' => 'oficial',
        'paralelo' => 'paralelo',
        'binance' => 'paralelo' // Binance no disponible, usar paralelo
    ];
    
    $key = $mapeo[$fuente] ?? 'paralelo';
    $valor = 0;
    
    foreach ($tasas as $tasa) {
        if (isset($tasa['fuente']) && $tasa['fuente'] === $key) {
            $valor = floatval($tasa['promedio']);
            break;
        }
    }
    
    if ($valor > 0) {
        $stmt = $conn->prepare("INSERT INTO tasas_cambio (fuente, valor) VALUES (?, ?)");
        $stmt->bind_param("sd", $fuente, $valor);
        $stmt->execute();
        $stmt->close();
    }
    
    return $valor;
}

function convertirABs($usd, $fuente = null) {
    global $conn;
    
    if ($fuente === null) {
        $result = $conn->query("SELECT valor FROM configuracion WHERE clave = 'moneda_fuente'");
        if ($result && $result->num_rows > 0) {
            $fuente = $result->fetch_assoc()['valor'];
        } else {
            $fuente = 'paralelo';
        }
    }
    
    $tasa = obtenerTasa($fuente);
    
    if ($tasa <= 0) {
        return 0;
    }
    
    return $usd * $tasa;
}

function formatearBs($monto) {
    return 'Bs. ' . number_format($monto, 2, ',', '.');
}

function obtenerInfoTasa() {
    global $conn;
    
    $result = $conn->query("SELECT valor FROM configuracion WHERE clave = 'moneda_fuente'");
    $fuente = 'paralelo';
    if ($result && $result->num_rows > 0) {
        $fuente = $result->fetch_assoc()['valor'];
    }
    
    $stmt = $conn->prepare("SELECT valor, updated_at FROM tasas_cambio WHERE fuente = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $fuente);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $info = [
        'fuente' => $fuente,
        'tasa' => 0,
        'actualizado' => null
    ];
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $info['tasa'] = floatval($row['valor']);
        $info['actualizado'] = $row['updated_at'];
    } else {
        $info['tasa'] = actualizarTasa($fuente);
        $info['actualizado'] = date('Y-m-d H:i:s');
    }
    $stmt->close();
    
    return $info;
}

function obtenerTodasLasTasas() {
    $tasas = obtenerTasasAPI();
    
    if (!$tasas) {
        return [];
    }
    
    $resultado = [];
    
    foreach ($tasas as $tasa) {
        $resultado[] = [
            'fuente' => $tasa['fuente'],
            'nombre' => $tasa['nombre'],
            'tasa' => floatval($tasa['promedio']),
            'cambio' => 0
        ];
    }
    
    return $resultado;
}

crearTablaTasas();