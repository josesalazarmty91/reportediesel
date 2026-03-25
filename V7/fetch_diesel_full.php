<?php
// fetch_diesel_full.php
// CORREGIDO: Solución a "Illegal mix of collations" agregando COLLATE en el JOIN

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Configuración y Conexión
    $configFile = __DIR__ . '/db_config.php';
    if (!file_exists($configFile)) throw new Exception("Falta db_config.php");
    require $configFile;

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error BD: " . $conn->connect_error);
    $conn->set_charset('utf8mb4');

    // 2. Filtros
    $month = $_GET['month'] ?? '';
    $unit = $_GET['unit'] ?? '';
    $sort = $_GET['sort'] ?? 'default';

    // 3. Consulta Maestra
    $sql = "SELECT 
                -- Estado de Conciliación
                CASE WHEN t1.id IS NOT NULL THEN 1 ELSE 0 END as conciliado,
                
                -- IDs
                t1.id as t1_id,
                t2.id as t2_id,

                -- DATOS PRINCIPALES (Desde Tableta)
                t2.unit_number,  
                DATE_FORMAT(t2.timestamp, '%Y-%m-%d') as t1_report_date,
                DATE_FORMAT(t2.timestamp, '%H:%i:%s') as t1_report_time,
                
                -- Datos Tableta Normalizados
                t2.operator_name as t2_operator_name,
                t2.bitacora_number,
                t2.km_inicio,
                t2.km_fin,
                (t2.km_fin - t2.km_inicio) as km_recorridos,
                
                COALESCE(t2.litros_diesel, 0) as litros_diesel,
                COALESCE(t2.litros_auto, 0) as litros_auto,
                t2.litros_urea,
                t2.litros_totalizador,
                t2.company_name as t2_company_name,
                t2.timestamp as t2_timestamp,

                -- Datos ECM (Pueden ser NULL)
                t1.distancia_conducida,
                t1.km_hubodometro,
                t1.km_recorrido,
                t1.combustible_viaje,
                t1.rendimiento_viaje,
                t1.tiempo_viaje,
                t1.tiempo_manejando,
                t1.tiempo_ralenti,
                t1.distancia_top_gear,
                t1.eventos_frenado,
                t1.rpm_maxima,
                t1.factor_carga,
                t1.def_usado,
                t1.travesia_km,
                t1.distancia_cambio_bajo,
                t1.combustible_manejando,
                t1.combustible_ralenti,
                t1.tiempo_top_gear,
                t1.tiempo_crucero,
                t1.tiempo_exceso_velocidad,
                t1.velocidad_maxima,
                t1.velocidad_promedio,
                t1.rendimiento_manejando,
                t1.eventos_exceso_velocidad,
                t1.tiempo_neutro_coasting,
                t1.tiempo_pto,
                t1.combustible_pto,
                t1.file_name

            -- FUENTE PRINCIPAL: TABLETA
            FROM (
                SELECT 
                    r.id, 
                    r.timestamp, 
                    r.bitacora_number,
                    r.km_inicio,
                    r.km_fin,
                    r.litros_diesel, 
                    r.litros_auto, 
                    r.litros_urea, 
                    r.litros_totalizador,
                    
                    IFNULL(u.unit_number, 'N/D') as unit_number,
                    IFNULL(c.name, 'N/D') as company_name,
                    IFNULL(o.name, 'N/D') as operator_name
                    
                FROM grupoam6_diesel.registros_entrada r
                LEFT JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                LEFT JOIN grupoam6_diesel.companies c ON r.company_id = c.id
                LEFT JOIN grupoam6_diesel.operators o ON r.operator_id = o.id
            ) as t2

            -- CRUCE CON ECM (Corrección de Collation AQUÍ)
            LEFT JOIN trip_reports as t1 
                ON t2.unit_number COLLATE utf8mb4_unicode_ci = t1.unit_number 
                AND DATE(t2.timestamp) = STR_TO_DATE(t1.report_date, '%m/%d/%Y')

            WHERE 1=1 ";

    // Filtros
    if (!empty($month)) {
        $safeMonth = $conn->real_escape_string($month);
        $sql .= " AND DATE_FORMAT(t2.timestamp, '%Y-%m') = '$safeMonth'";
    }
    if (!empty($unit)) {
        $safeUnit = $conn->real_escape_string($unit);
        $sql .= " AND t2.unit_number LIKE '%$safeUnit%'";
    }

    // Orden
    $sql .= ($sort === 'date_asc') ? " ORDER BY t2.timestamp ASC" : " ORDER BY t2.timestamp DESC";

    $result = $conn->query($sql);
    if (!$result) throw new Exception("Error SQL: " . $conn->error);

    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => $e->getMessage()]);
}
?>