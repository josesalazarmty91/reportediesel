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
        $whereClauses[] = "DATE_FORMAT(STR_TO_DATE(t1.report_date, '%m/%d/%Y'), '%Y-%m') = '{$conn->real_escape_string($month)}'";
    }
    if (!empty($unit)) {
        $whereClauses[] = "t1.unit_number LIKE '%{$conn->real_escape_string($unit)}%'";
    }

    $sqlBase = "
        SELECT 
            CASE WHEN t2.id IS NOT NULL THEN 1 ELSE 0 END as conciliado,
            t1.id as t1_id,
            t1.file_name,
            t1.unit_number,
            CASE WHEN t1.report_date IS NULL OR t1.report_date = 'N/D' THEN 'N/D'
                 ELSE DATE_FORMAT(STR_TO_DATE(t1.report_date, '%m/%d/%Y'), '%d/%m/%Y') END AS t1_report_date,
            t1.report_time as t1_report_time,
            t1.km_recorrido,
            t1.distancia_conducida,
            t1.distancia_top_gear,
            t1.distancia_cambio_bajo,
            t1.combustible_viaje,
            t1.combustible_manejando,
            t1.combustible_ralenti,
            t1.def_usado,
            t1.tiempo_viaje,
            t1.tiempo_manejando,
            t1.tiempo_ralenti,
            t1.tiempo_top_gear,
            t1.tiempo_crucero,
            t1.tiempo_exceso_velocidad,
            t1.velocidad_maxima,
            t1.rpm_maxima,
            t1.velocidad_promedio,
            t1.rendimiento_viaje,
            t1.rendimiento_manejando,
            t1.factor_carga,
            t1.eventos_exceso_velocidad,
            t1.eventos_frenado,
            t1.tiempo_neutro_coasting,
            t1.tiempo_pto,
            t1.combustible_pto,
            t1.km_hubodometro,
            t1.travesia_km,

            t2.id as t2_id,
            IFNULL(c.name, 'N/D') as t2_company_name,
            IFNULL(t2.unit_number, 'N/D') as t2_unit_number,
            DATE_FORMAT(t2.timestamp, '%d/%m/%Y %H:%i:%s') as t2_timestamp,
            IFNULL(o.name, 'N/D') as t2_operator_name,
            t2.bitacora_number,
            t2.km_inicio,
            t2.km_fin,
            t2.km_recorridos,
            t2.litros_diesel,
            t2.litros_auto, -- <--- NUEVO CAMPO
            t2.litros_urea,
            t2.litros_totalizador
            
        FROM trip_reports as t1
        LEFT JOIN (
            SELECT r_sub.*, u_sub.unit_number
            FROM grupoam6_diesel.registros_entrada as r_sub
            LEFT JOIN grupoam6_diesel.units as u_sub ON r_sub.unit_id = u_sub.id
        ) as t2 ON t1.unit_number = t2.unit_number COLLATE utf8mb4_unicode_ci
               AND STR_TO_DATE(t1.report_date, '%m/%d/%Y') = CAST(t2.timestamp AS DATE)
        LEFT JOIN grupoam6_diesel.companies as c ON t2.company_id = c.id
        LEFT JOIN grupoam6_diesel.operators as o ON t2.operator_id = o.id
    ";

    $sqlOrder = " ORDER BY t1.id DESC ";
    if (!empty($whereClauses)) {
        $sql = $sqlBase . " WHERE " . implode(" AND ", $whereClauses) . $sqlOrder;
    } else {
        $sql = $sqlBase . $sqlOrder;
    }

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