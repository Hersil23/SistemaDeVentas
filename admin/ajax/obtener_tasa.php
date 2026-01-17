<?php
/**
 * Obtener tasas de cambio desde diferentes fuentes
 * - Paralelo (Monitor Dólar / pyDolarVenezuela)
 * - BCV (Banco Central de Venezuela)
 * - Binance P2P
 */

header('Content-Type: application/json');

$fuente = $_GET['fuente'] ?? 'paralelo';

try {
    switch ($fuente) {
        case 'paralelo':
            $tasa = obtenerTasaParalelo();
            break;
        case 'bcv':
            $tasa = obtenerTasaBCV();
            break;
        case 'binance':
            $tasa = obtenerTasaBinance();
            break;
        default:
            throw new Exception('Fuente no válida');
    }
    
    if ($tasa) {
        echo json_encode([
            'success' => true,
            'tasa' => number_format($tasa, 2, '.', ''),
            'fuente' => $fuente,
            'fecha' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('No se pudo obtener la tasa');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtener tasa del dólar paralelo desde pyDolarVenezuela API
 */
function obtenerTasaParalelo() {
    // API pública de pyDolarVenezuela
    $url = 'https://pydolarve.org/api/v1/dollar?page=alcambio';
    
    $response = hacerPeticion($url);
    
    if ($response) {
        $data = json_decode($response, true);
        // La estructura puede variar, intentamos varias opciones
        if (isset($data['monitors']['alcambio']['price'])) {
            return floatval($data['monitors']['alcambio']['price']);
        }
        if (isset($data['price'])) {
            return floatval($data['price']);
        }
    }
    
    // Alternativa: API de ExchangeMonitor
    $url2 = 'https://ve.dolarapi.com/v1/dolares/paralelo';
    $response2 = hacerPeticion($url2);
    
    if ($response2) {
        $data2 = json_decode($response2, true);
        if (isset($data2['promedio'])) {
            return floatval($data2['promedio']);
        }
    }
    
    return null;
}

/**
 * Obtener tasa oficial del BCV
 */
function obtenerTasaBCV() {
    // API pública de pyDolarVenezuela para BCV
    $url = 'https://pydolarve.org/api/v1/dollar?page=bcv';
    
    $response = hacerPeticion($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['monitors']['bcv']['price'])) {
            return floatval($data['monitors']['bcv']['price']);
        }
        if (isset($data['price'])) {
            return floatval($data['price']);
        }
    }
    
    // Alternativa: dolarapi
    $url2 = 'https://ve.dolarapi.com/v1/dolares/oficial';
    $response2 = hacerPeticion($url2);
    
    if ($response2) {
        $data2 = json_decode($response2, true);
        if (isset($data2['promedio'])) {
            return floatval($data2['promedio']);
        }
    }
    
    return null;
}

/**
 * Obtener tasa de Binance P2P (USDT/VES)
 */
function obtenerTasaBinance() {
    // API oficial de Binance P2P
    $url = 'https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search';
    
    $postData = json_encode([
        'fiat' => 'VES',
        'page' => 1,
        'rows' => 10,
        'tradeType' => 'BUY',
        'asset' => 'USDT',
        'countries' => [],
        'proMerchantAds' => false,
        'shieldMerchantAds' => false,
        'publisherType' => null,
        'payTypes' => []
    ]);
    
    $response = hacerPeticion($url, $postData);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['data']) && count($data['data']) > 0) {
            // Obtener promedio de los primeros 5 anuncios
            $precios = [];
            foreach (array_slice($data['data'], 0, 5) as $ad) {
                if (isset($ad['adv']['price'])) {
                    $precios[] = floatval($ad['adv']['price']);
                }
            }
            if (count($precios) > 0) {
                return array_sum($precios) / count($precios);
            }
        }
    }
    
    // Alternativa: usar pyDolarVenezuela para Binance
    $url2 = 'https://pydolarve.org/api/v1/dollar?page=binance';
    $response2 = hacerPeticion($url2);
    
    if ($response2) {
        $data2 = json_decode($response2, true);
        if (isset($data2['monitors']['binance']['price'])) {
            return floatval($data2['monitors']['binance']['price']);
        }
    }
    
    return null;
}

/**
 * Hacer petición HTTP con cURL
 */
function hacerPeticion($url, $postData = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return $response;
    }
    
    return null;
}