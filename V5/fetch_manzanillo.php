<?php
// fetch_manzanillo.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
require 'db_config.php';

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');

    $month = $_GET['month'] ?? '';
    
    $sql = "SELECT id, unidad, fecha_carga, hora_carga, litros_diesel, nombre_archivo_origen, timestamp 
            FROM registros_manzanillo ";
            
    if (!empty($month)) {
        // Asumiendo formato d/m/Y en fecha_carga, convertimos para filtrar
        $sql .= " WHERE DATE_FORMAT(STR_TO_DATE(fecha_carga, '%d/%m/%Y'), '%Y-%m') = '" . $conn->real_escape_string($month) . "'";
    }
    
    $sql .= " ORDER BY id DESC LIMIT 500"; // Limite por seguridad de rendimiento

    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    $conn->close();
} catch (Exception $e) {
    echo json_encode([]);
}
?>