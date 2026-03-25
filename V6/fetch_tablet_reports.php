<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    $configFile = __DIR__ . '/db_config.php';
    if (!file_exists($configFile)) throw new Exception("Falta db_config.php");
    require $configFile;

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error BD: " . $conn->connect_error);
    $conn->set_charset('utf8mb4');

    $month = $_GET['month'] ?? null;
    $unit = $_GET['unit'] ?? null;

    $whereClauses = [];
    if (!empty($month)) {
        $whereClauses[] = "DATE_FORMAT(timestamp, '%Y-%m') = '{$conn->real_escape_string($month)}'";
    }
    if (!empty($unit)) {
        // Buscamos por nombre de unidad en la tabla relacionada
        $whereClauses[] = "u.unit_number LIKE '%{$conn->real_escape_string($unit)}%'";
    }

    // Consulta con JOINs para obtener nombres legibles y el nuevo campo litros_auto
    $sql = "
        SELECT 
            r.id,
            IFNULL(c.name, 'N/D') as company_name,
            IFNULL(u.unit_number, 'N/D') as unit_number,
            DATE_FORMAT(r.timestamp, '%d/%m/%Y %H:%i:%s') as timestamp,
            IFNULL(o.name, 'N/D') as operator_name,
            r.bitacora_number,
            r.km_inicio,
            r.km_fin,
            r.km_recorridos,
            r.litros_diesel,
            r.litros_auto, -- <--- NUEVO CAMPO
            r.litros_urea,
            r.litros_totalizador
        FROM grupoam6_diesel.registros_entrada r
        LEFT JOIN grupoam6_diesel.companies c ON r.company_id = c.id
        LEFT JOIN grupoam6_diesel.units u ON r.unit_id = u.id
        LEFT JOIN grupoam6_diesel.operators o ON r.operator_id = o.id
    ";

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY r.id DESC";

    $result = $conn->query($sql);
    if (!$result) throw new Exception("Error SQL: " . $conn->error);

    $reports = [];
    while($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    echo json_encode($reports);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}
?>