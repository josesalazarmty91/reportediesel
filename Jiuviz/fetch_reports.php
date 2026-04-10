<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

$response = [];

try {
    $configFile = __DIR__ . '/db_config.php';
    if (!file_exists($configFile))
        throw new Exception("Falta db_config.php");
    require $configFile;

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error)
        throw new Exception("Error BD: " . $conn->connect_error);
    $conn->set_charset('utf8mb4');

    $month = $_GET['month'] ?? null;
    $unit = $_GET['unit'] ?? null;
    $sortBy = $_GET['sort_by'] ?? 'id';
    $sortDir = $_GET['sort_dir'] ?? 'DESC';
    $sortDir = (strtoupper($sortDir) === 'DESC') ? 'DESC' : 'ASC';

    $whereClauses = [];
    if (!empty($month)) {
        $whereClauses[] = "DATE_FORMAT(STR_TO_DATE(report_date, '%m/%d/%Y'), '%Y-%m') = '{$conn->real_escape_string($month)}'";
    }
    if (!empty($unit)) {
        $whereClauses[] = "unit_number LIKE '%{$conn->real_escape_string($unit)}%'";
    }

    $orderByClause = " ORDER BY id $sortDir";
    if ($sortBy === 'date') {
        $orderByClause = " ORDER BY CASE WHEN STR_TO_DATE(report_date, '%m/%d/%Y') IS NULL THEN 1 ELSE 0 END, STR_TO_DATE(report_date, '%m/%d/%Y') $sortDir, report_time $sortDir";
    }

    $sql = "
        SELECT 
            id, file_name, unit_number,
            CASE WHEN report_date IS NULL OR report_date = 'N/D' THEN 'N/D'
                 ELSE DATE_FORMAT(STR_TO_DATE(report_date, '%m/%d/%Y'), '%d/%m/%Y') END AS report_date,
            report_time,
            km_recorrido,
            distancia_conducida,
            distancia_top_gear,
            distancia_cambio_bajo,
            combustible_viaje,
            combustible_manejando,
            combustible_ralenti,
            def_usado,
            tiempo_viaje,
            tiempo_manejando,
            tiempo_ralenti,
            tiempo_top_gear,
            tiempo_crucero,
            tiempo_exceso_velocidad,
            velocidad_maxima,
            rpm_maxima,
            velocidad_promedio,
            rendimiento_viaje,
            rendimiento_manejando,
            factor_carga,
            eventos_exceso_velocidad,
            eventos_frenado,
            frenadas_firmes,
            frenados_fuertes,
            tiempo_neutro_coasting,
            tiempo_pto,
            combustible_pto,
            km_hubodometro,
            travesia_km -- <--- CAMPO NUEVO
        FROM trip_reports
    ";

    if (!empty($whereClauses))
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    $sql .= $orderByClause;

    $result = $conn->query($sql);
    if (!$result)
        throw new Exception("Error SQL: " . $conn->error);

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    echo json_encode($reports);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>