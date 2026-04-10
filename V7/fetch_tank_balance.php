<?php
// fetch_tank_balance.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';
ini_set('display_errors', 0);
error_reporting(0);

$month = $_GET['month'] ?? date('Y-m'); // YYYY-MM

$response = ['status' => 'error', 'message' => '', 'daily' => [], 'audit' => []];

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error)
        throw new Exception("Error conexión BD");
    $conn->set_charset("utf8mb4");

    // 1. OBTENER TOMAS (Incluyendo la última del mes anterior para cerrar el primer ciclo)
    // Buscamos tomas desde el mes previo para asegurar que el día 1 tenga referencia si hubo toma el día 30 pasado.
    $prevMonth = date('Y-m', strtotime("$month-01 -1 month"));

    $sqlTomas = "SELECT litros, fecha, hora, CONCAT(fecha, ' ', hora) as fecha_hora 
                 FROM registro_tomas 
                 WHERE DATE_FORMAT(fecha, '%Y-%m') >= '$prevMonth' 
                   AND DATE_FORMAT(fecha, '%Y-%m') <= '$month'
                 ORDER BY fecha ASC, hora ASC";

    $resTomas = $conn->query($sqlTomas);
    $allTomas = [];
    while ($row = $resTomas->fetch_assoc()) {
        $allTomas[] = $row;
    }

    // 2. OBTENER CONSUMO DIARIO (Para la línea de fondo)
    $sqlDaily = "SELECT DATE_FORMAT(timestamp, '%Y-%m-%d') as dia, SUM(litros_diesel) as total 
                 FROM grupoam6_diesel_jiuviz.registros_entrada 
                 WHERE DATE_FORMAT(timestamp, '%Y-%m') = '$month'
                 GROUP BY dia ORDER BY dia ASC";
    $resDaily = $conn->query($sqlDaily);
    $dailyData = [];
    while ($row = $resDaily->fetch_assoc()) {
        $dailyData[] = $row;
    }

    // 3. CALCULAR DISCREPANCIAS (AUDITORÍA)
    // Recorremos las tomas pares: Toma Anterior -> Toma Actual
    $auditData = [];

    // Solo procesamos si hay al menos 2 tomas en el rango ampliado para comparar
    if (count($allTomas) >= 2) {
        for ($i = 1; $i < count($allTomas); $i++) {
            $prev = $allTomas[$i - 1];
            $curr = $allTomas[$i];

            // Solo nos interesan los cortes que terminan en el MES seleccionado
            if (substr($curr['fecha'], 0, 7) !== $month)
                continue;

            // A. Consumo Físico (Lo que bajó el tanque real)
            // Nota: Si hubo recarga de pipa, esto daría negativo (subió el nivel). 
            // Asumimos por ahora solo consumo de salida.
            $consumoFisico = floatval($prev['litros']) - floatval($curr['litros']);

            // B. Consumo Digital (Suma de tabletas en ese rango de tiempo exacto)
            $start = $prev['fecha_hora'];
            $end = $curr['fecha_hora'];

            $sqlConsumoRango = "SELECT SUM(litros_diesel) as total 
                                FROM grupoam6_diesel_jiuviz.registros_entrada 
                                WHERE timestamp > '$start' AND timestamp <= '$end'";

            $resR = $conn->query($sqlConsumoRango);
            $rowR = $resR->fetch_assoc();
            $consumoDigital = floatval($rowR['total'] ?? 0);

            // C. Discrepancia (Faltante)
            // Ejemplo: Bajó 1000L (Físico), Reportaron 900L (Digital) -> Faltan 100L (Positivo)
            // Si reportaron 1100L -> Sobran -100L (Negativo)
            $discrepancia = $consumoFisico - $consumoDigital;

            $auditData[] = [
                'fecha_corte' => $curr['fecha'], // Fecha donde se detecta la diferencia
                'hora_corte' => $curr['hora'],
                'nivel_real' => $curr['litros'],
                'consumo_fisico' => $consumoFisico,
                'consumo_digital' => $consumoDigital,
                'discrepancia' => round($discrepancia, 2)
            ];
        }
    }

    $response['status'] = 'success';
    $response['daily'] = $dailyData; // Para pintar consumo diario
    $response['audit'] = $auditData; // Para pintar la línea de discrepancia

    $conn->close();

}
catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>