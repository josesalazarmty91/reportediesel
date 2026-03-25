<?php
// fetch_tank_balance.php
// Calcula el nivel diario del tanque cruzando Tomas Físicas vs Consumo Tableta

header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';

$month = $_GET['month'] ?? date('Y-m'); // YYYY-MM
$tankCapacity = 30000;

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión BD");
    $conn->set_charset("utf8mb4");

    // 1. Obtener la ÚLTIMA TOMA antes del mes seleccionado (Saldo Inicial)
    // Buscamos en la tabla de tomas (local)
    $sqlInitial = "SELECT litros, CONCAT(fecha, ' ', hora) as fecha_hora 
                   FROM registro_tomas 
                   WHERE DATE_FORMAT(fecha, '%Y-%m') < '$month' 
                   ORDER BY fecha DESC, hora DESC LIMIT 1";
    
    $resInit = $conn->query($sqlInitial);
    $currentLevel = 0;
    $lastTomaDate = null; // Para saber desde cuándo restar consumos

    if ($resInit && $row = $resInit->fetch_assoc()) {
        $currentLevel = (float)$row['litros'];
        $lastTomaDate = $row['fecha_hora'];
    }

    // 2. Obtener TODAS las Tomas del mes seleccionado
    $tomasDelMes = [];
    $sqlTomas = "SELECT litros, CONCAT(fecha, ' ', hora) as fecha_hora 
                 FROM registro_tomas 
                 WHERE DATE_FORMAT(fecha, '%Y-%m') = '$month' 
                 ORDER BY fecha ASC, hora ASC";
    $resTomas = $conn->query($sqlTomas);
    while($row = $resTomas->fetch_assoc()) {
        $tomasDelMes[] = $row;
    }

    // 3. Obtener TODOS los consumos (Tableta) relevantes
    // Necesitamos consumos desde la ultima toma previa (si existe) hasta fin de mes
    $startDate = $lastTomaDate ? date('Y-m-d', strtotime($lastTomaDate)) : "$month-01";
    
    // Consulta Cross-Database a grupoam6_diesel
    $sqlConsumos = "SELECT timestamp, litros_diesel 
                    FROM grupoam6_diesel.registros_entrada 
                    WHERE timestamp >= '$startDate 00:00:00' 
                    AND DATE_FORMAT(timestamp, '%Y-%m') <= '$month'
                    ORDER BY timestamp ASC";
                    
    $resConsumos = $conn->query($sqlConsumos);
    $consumos = [];
    while($row = $resConsumos->fetch_assoc()) {
        $consumos[] = $row;
    }

    // 4. Procesamiento Día a Día
    $daysInMonth = date('t', strtotime("$month-01"));
    $dailyData = [];
    
    // Unificar eventos en una línea de tiempo
    // Eventos: { tipo: 'toma'|'consumo', fecha_hora: '...', valor: N }
    $timeline = [];
    
    // Agregar Tomas al timeline
    foreach ($tomasDelMes as $t) {
        $timeline[] = ['type' => 'toma', 'time' => $t['fecha_hora'], 'val' => (float)$t['litros']];
    }
    // Agregar Consumos al timeline
    foreach ($consumos as $c) {
        // Solo agregamos consumos POSTERIORES a la toma inicial (si hubo)
        if (!$lastTomaDate || $c['timestamp'] > $lastTomaDate) {
            $timeline[] = ['type' => 'consumo', 'time' => $c['timestamp'], 'val' => (float)$c['litros_diesel']];
        }
    }

    // Ordenar timeline por fecha
    usort($timeline, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });

    // Simular el mes día a día
    $timelineIndex = 0;
    $totalEvents = count($timeline);

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $currentDateStr = sprintf("%s-%02d", $month, $d);
        $dayEnd = "$currentDateStr 23:59:59";

        // Procesar eventos hasta el final de este día
        while ($timelineIndex < $totalEvents && $timeline[$timelineIndex]['time'] <= $dayEnd) {
            $evt = $timeline[$timelineIndex];
            
            if ($evt['type'] === 'toma') {
                $currentLevel = $evt['val']; // Resetear nivel
            } else {
                $currentLevel -= $evt['val']; // Restar consumo
            }
            
            // Evitar negativos lógicos
            if ($currentLevel < 0) $currentLevel = 0;
            
            $timelineIndex++;
        }

        $dailyData[] = [
            'dia' => $d,
            'nivel' => round($currentLevel, 2),
            'vacio' => round($tankCapacity - $currentLevel, 2)
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $dailyData]);
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>