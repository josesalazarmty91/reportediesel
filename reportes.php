<?php
// reportes.php
// Configuración para que SIEMPRE devuelva JSON, incluso si hay error
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'db_config.php';

$action = $_GET['action'] ?? '';
$unit = $_GET['unit'] ?? '';
$month = $_GET['month'] ?? '';

$response = [];

try {
    // Conexión
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la BD: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    switch ($action) {
        case 'getMonthlyStats':
            $sql = "SELECT 
                        DATE_FORMAT(STR_TO_DATE(t1.report_date, '%m/%d/%Y'), '%Y-%m') as mes,
                        SUM(COALESCE(t2.litros_diesel, 0) + COALESCE(t2.litros_auto, 0)) as total_diesel_bases,
                        AVG(CAST(REPLACE(t1.rendimiento_viaje, ',', '') AS DECIMAL(10,2))) as promedio_rendimiento
                    FROM trip_reports t1
                    LEFT JOIN grupoam6_diesel.registros_entrada t2 
                        ON t1.unit_number = t2.unit_number 
                        AND STR_TO_DATE(t1.report_date, '%m/%d/%Y') = t2.timestamp
                    WHERE t1.report_date != 'N/D' ";
            
            if (!empty($unit)) {
                $safeUnit = $conn->real_escape_string($unit);
                $sql .= " AND t1.unit_number LIKE '%$safeUnit%' ";
            }
            $sql .= " GROUP BY mes ORDER BY mes ASC";

            $result = $conn->query($sql);

            if (!$result) {
                error_log("Error SQL Cruzado: " . $conn->error . ". Usando fallback local.");
                
                $sqlFallback = "SELECT 
                                    DATE_FORMAT(STR_TO_DATE(report_date, '%m/%d/%Y'), '%Y-%m') as mes,
                                    SUM(CAST(REPLACE(combustible_viaje, ',', '') AS DECIMAL(10,2))) as total_diesel_bases,
                                    AVG(CAST(REPLACE(rendimiento_viaje, ',', '') AS DECIMAL(10,2))) as promedio_rendimiento
                                FROM trip_reports
                                WHERE report_date != 'N/D' ";
                if (!empty($unit)) {
                    $safeUnit = $conn->real_escape_string($unit);
                    $sqlFallback .= " AND unit_number LIKE '%$safeUnit%' ";
                }
                $sqlFallback .= " GROUP BY mes ORDER BY mes ASC";
                
                $result = $conn->query($sqlFallback);
            }

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $response[] = $row; 
                }
            }
            break;

        case 'getManzanilloDailyStats':
            $sql = "SELECT 
                        DATE_FORMAT(STR_TO_DATE(fecha_carga, '%d/%m/%Y'), '%Y-%m-%d') as dia,
                        SUM(litros_diesel) as total_litros
                    FROM registros_manzanillo 
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(STR_TO_DATE(fecha_carga, '%d/%m/%Y'), '%Y-%m') = '$safeMonth' ";
            }

            $sql .= " GROUP BY dia ORDER BY dia ASC";
            
            $result = $conn->query($sql);
            $totalAcumulado = 0;
            $days = [];
            
            if($result) {
                while($row = $result->fetch_assoc()) {
                    $totalAcumulado += floatval($row['total_litros']);
                    $days[] = $row;
                }
            }
            
            echo json_encode(['data' => $days, 'total_acumulado' => $totalAcumulado]);
            exit; 
            
        case 'getTabletStats':
            $month = $_GET['month'] ?? ''; 

            $sql = "SELECT 
            DATE_FORMAT(timestamp, '%Y-%m-%d') as dia,
            SUM(COALESCE(litros_diesel, 0)) as total_diesel
            FROM grupoam6_diesel.registros_entrada 
            WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(timestamp, '%Y-%m') = '$safeMonth' ";
            }

            $sql .= " GROUP BY dia ORDER BY dia ASC";
            
            $result = $conn->query($sql);
            
            $totalAcumulado = 0;
            $days = [];
            
            if($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_diesel']);
                    $totalAcumulado += $val;
                    $days[] = [
                        'dia' => $row['dia'],
                        'total' => $val
                    ];
                }
            }
            
            echo json_encode(['data' => $days, 'total_acumulado' => $totalAcumulado]);
            exit;

        case 'getUnitsByMonth':
            $month = $_GET['month'] ?? '';
            $sql = "SELECT DISTINCT unit_number 
                    FROM trip_reports 
                    WHERE report_date != 'N/D' ";
            
            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(STR_TO_DATE(report_date, '%m/%d/%Y'), '%Y-%m') = '$safeMonth' ";
            }
            $sql .= " ORDER BY unit_number ASC";
            
            $result = $conn->query($sql);
            $units = [];
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    if($row['unit_number']) $units[] = $row['unit_number'];
                }
            }
            echo json_encode($units);
            exit;

        case 'getKmStats':
            $month = $_GET['month'] ?? '';
            $unitsStr = $_GET['units'] ?? ''; 
            
            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) {
                    $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'";
                }
                if(count($cleanUnits) > 0) {
                    $unitFilter = " AND unit_number IN (" . implode(',', $cleanUnits) . ") ";
                }
            }

            $sql = "SELECT 
                        unit_number as unit,
                        SUM(CAST(REPLACE(km_recorrido, ',', '') AS DECIMAL(10,2))) as total_km
                    FROM trip_reports
                    WHERE report_date != 'N/D' ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(STR_TO_DATE(report_date, '%m/%d/%Y'), '%Y-%m') = '$safeMonth' ";
            }

            if (!empty($unitFilter)) {
                $sql .= $unitFilter;
            }

            $sql .= " GROUP BY unit_number ORDER BY total_km DESC"; 

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_km']);
                    $totalMes += $val;
                    $data[] = [
                        'unit' => $row['unit'],
                        'km' => $val
                    ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

        case 'getDieselUnitsByMonth':
            $month = $_GET['month'] ?? '';
            $sql = "SELECT DISTINCT u.unit_number 
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    WHERE 1=1 ";
            
            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }
            $sql .= " ORDER BY u.unit_number ASC";
            
            $result = $conn->query($sql);
            $units = [];
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    if($row['unit_number']) $units[] = $row['unit_number'];
                }
            }
            echo json_encode($units);
            exit;

        case 'getDieselStatsByUnit':
            $month = $_GET['month'] ?? '';
            $unitsStr = $_GET['units'] ?? ''; 

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) {
                    $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'";
                }
                if(count($cleanUnits) > 0) {
                    $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") ";
                }
            }

            $sql = "SELECT 
                        u.unit_number as unit,
                        SUM(COALESCE(r.litros_diesel, 0) + COALESCE(r.litros_auto, 0)) as total_fuel
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }

            if (!empty($unitFilter)) {
                $sql .= $unitFilter;
            }

            $sql .= " GROUP BY u.unit_number ORDER BY total_fuel DESC";

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_fuel']);
                    $totalMes += $val;
                    $data[] = [
                        'unit' => $row['unit'],
                        'fuel' => $val
                    ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

        // --- Obtener Flotas y Unidades cruzadas para los filtros dinámicos ---
        case 'getFleetFilterData':
            $month = $_GET['month'] ?? '';
            $safeMonth = $conn->real_escape_string($month);
            
            $sqlFleets = "SELECT DISTINCT IFNULL(f.name, 'Sin Flota') as name 
                          FROM grupoam6_diesel.registros_entrada r 
                          JOIN grupoam6_diesel.units u ON r.unit_id = u.id 
                          LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                          WHERE DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth'
                          ORDER BY name ASC";
            $resFleets = $conn->query($sqlFleets);
            $fleets = [];
            if ($resFleets) { while($row = $resFleets->fetch_assoc()) { $fleets[] = $row['name']; } }

            $sqlUnits = "SELECT DISTINCT u.unit_number, IFNULL(f.name, 'Sin Flota') as fleet_name 
                         FROM grupoam6_diesel.registros_entrada r 
                         JOIN grupoam6_diesel.units u ON r.unit_id = u.id 
                         LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                         WHERE DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' 
                         ORDER BY u.unit_number ASC";
            $resUnits = $conn->query($sqlUnits);
            $units = [];
            if ($resUnits) { 
                while($row = $resUnits->fetch_assoc()) { 
                    $units[] = [
                        'unit' => $row['unit_number'],
                        'fleet' => $row['fleet_name']
                    ]; 
                } 
            }

            echo json_encode(['units' => $units, 'fleets' => $fleets]);
            exit;

        // --- Gráfica Consumo Diesel por FLOTA (Solo Tableta) ---
        case 'getDieselStatsByFleet':
            $month = $_GET['month'] ?? '';
            $fleetsStr = $_GET['fleets'] ?? ''; 
            $unitsStr = $_GET['units'] ?? '';

            $fleetFilter = "";
            if (!empty($fleetsStr)) {
                $fleetsArray = explode(',', $fleetsStr);
                $cleanFleets = [];
                foreach($fleetsArray as $f) { $cleanFleets[] = "'" . $conn->real_escape_string(trim($f)) . "'"; }
                if(count($cleanFleets) > 0) { $fleetFilter = " AND IFNULL(f.name, 'Sin Flota') IN (" . implode(',', $cleanFleets) . ") "; }
            }

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) { $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'"; }
                if(count($cleanUnits) > 0) { $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") "; }
            }

            $sql = "SELECT 
                        IFNULL(f.name, 'Sin Flota') as flota,
                        SUM(COALESCE(r.litros_diesel, 0)) as total_fuel
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }

            $sql .= $fleetFilter . $unitFilter;
            $sql .= " GROUP BY f.id, f.name ORDER BY total_fuel DESC";

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_fuel']);
                    $totalMes += $val;
                    $data[] = [ 'flota' => $row['flota'], 'fuel' => $val ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

        // --- Gráfica KM Recorridos por FLOTA (Tableta) ---
        case 'getKmStatsByFleet':
            $month = $_GET['month'] ?? '';
            $fleetsStr = $_GET['fleets'] ?? ''; 
            $unitsStr = $_GET['units'] ?? '';

            $fleetFilter = "";
            if (!empty($fleetsStr)) {
                $fleetsArray = explode(',', $fleetsStr);
                $cleanFleets = [];
                foreach($fleetsArray as $f) { $cleanFleets[] = "'" . $conn->real_escape_string(trim($f)) . "'"; }
                if(count($cleanFleets) > 0) { $fleetFilter = " AND IFNULL(f.name, 'Sin Flota') IN (" . implode(',', $cleanFleets) . ") "; }
            }

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) { $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'"; }
                if(count($cleanUnits) > 0) { $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") "; }
            }

            $sql = "SELECT 
                        IFNULL(f.name, 'Sin Flota') as flota,
                        SUM(CAST(REPLACE(r.km_recorridos, ',', '') AS DECIMAL(10,2))) as total_km
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }

            $sql .= $fleetFilter . $unitFilter;
            $sql .= " GROUP BY f.id, f.name ORDER BY total_km DESC";

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_km']);
                    $totalMes += $val;
                    $data[] = [ 'flota' => $row['flota'], 'km' => $val ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

        // --- Gráfica Forecast vs KM Recorrido Diario (Tableta) ---
        case 'getKmForecastStats':
            $month = $_GET['month'] ?? '';
            $fleetsStr = $_GET['fleets'] ?? ''; 
            $unitsStr = $_GET['units'] ?? '';

            $fleetFilter = "";
            if (!empty($fleetsStr)) {
                $fleetsArray = explode(',', $fleetsStr);
                $cleanFleets = [];
                foreach($fleetsArray as $f) { $cleanFleets[] = "'" . $conn->real_escape_string(trim($f)) . "'"; }
                if(count($cleanFleets) > 0) { $fleetFilter = " AND IFNULL(f.name, 'Sin Flota') IN (" . implode(',', $cleanFleets) . ") "; }
            }

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) { $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'"; }
                if(count($cleanUnits) > 0) { $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") "; }
            }

            $sql = "SELECT 
                        DATE_FORMAT(r.timestamp, '%Y-%m-%d') as dia,
                        SUM(CAST(REPLACE(r.km_recorridos, ',', '') AS DECIMAL(10,2))) as total_km
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }

            $sql .= $fleetFilter . $unitFilter;
            $sql .= " GROUP BY dia ORDER BY dia ASC";

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_km']);
                    $totalMes += $val;
                    $data[] = [ 'dia' => $row['dia'], 'km' => $val ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

        // --- Gráfica Drill-Down (KM por Unidad en un Día específico) ---
        case 'getKmStatsByUnitDay':
            $day = $_GET['day'] ?? ''; 
            $fleetsStr = $_GET['fleets'] ?? ''; 
            $unitsStr = $_GET['units'] ?? '';

            $fleetFilter = "";
            if (!empty($fleetsStr)) {
                $fleetsArray = explode(',', $fleetsStr);
                $cleanFleets = [];
                foreach($fleetsArray as $f) { $cleanFleets[] = "'" . $conn->real_escape_string(trim($f)) . "'"; }
                if(count($cleanFleets) > 0) { $fleetFilter = " AND IFNULL(f.name, 'Sin Flota') IN (" . implode(',', $cleanFleets) . ") "; }
            }

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) { $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'"; }
                if(count($cleanUnits) > 0) { $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") "; }
            }

            $sql = "SELECT 
                        u.unit_number as unit,
                        SUM(CAST(REPLACE(r.km_recorridos, ',', '') AS DECIMAL(10,2))) as total_km
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                    WHERE 1=1 ";

            if (!empty($day)) {
                $safeDay = $conn->real_escape_string($day);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m-%d') = '$safeDay' ";
            }

            $sql .= $fleetFilter . $unitFilter;
            $sql .= " GROUP BY u.unit_number ORDER BY total_km DESC";

            $result = $conn->query($sql);
            $data = [];
            $totalDia = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_km']);
                    $totalDia += $val;
                    $data[] = [ 'unit' => $row['unit'], 'km' => $val ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalDia]);
            exit;

        // --- NUEVO: Gráfica Rendimiento Tableta (Drill-Down: Flota -> Unidad) ---
        case 'getTabletRendimientoStats':
            $month = $_GET['month'] ?? '';
            $fleetsStr = $_GET['fleets'] ?? ''; 
            $unitsStr = $_GET['units'] ?? '';
            $groupBy = $_GET['groupBy'] ?? 'fleet'; // 'fleet' o 'unit'

            $fleetFilter = "";
            if (!empty($fleetsStr)) {
                $fleetsArray = explode(',', $fleetsStr);
                $cleanFleets = [];
                foreach($fleetsArray as $f) { $cleanFleets[] = "'" . $conn->real_escape_string(trim($f)) . "'"; }
                if(count($cleanFleets) > 0) { $fleetFilter = " AND IFNULL(f.name, 'Sin Flota') IN (" . implode(',', $cleanFleets) . ") "; }
            }

            $unitFilter = "";
            if (!empty($unitsStr)) {
                $unitsArray = explode(',', $unitsStr);
                $cleanUnits = [];
                foreach($unitsArray as $u) { $cleanUnits[] = "'" . $conn->real_escape_string(trim($u)) . "'"; }
                if(count($cleanUnits) > 0) { $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") "; }
            }

            if ($groupBy === 'fleet') {
                $select = "IFNULL(f.name, 'Sin Flota') as label";
                $groupByClause = " GROUP BY f.id, f.name ";
            } else {
                $select = "u.unit_number as label";
                $groupByClause = " GROUP BY u.unit_number ";
            }

            $sql = "SELECT 
                        $select,
                        SUM(CAST(REPLACE(r.km_recorridos, ',', '') AS DECIMAL(10,2))) as sum_km,
                        SUM(COALESCE(r.litros_diesel, 0) + COALESCE(r.litros_auto, 0)) as sum_lts
                    FROM grupoam6_diesel.registros_entrada r
                    JOIN grupoam6_diesel.units u ON r.unit_id = u.id
                    LEFT JOIN grupoam6_diesel.families f ON u.family_id = f.id
                    WHERE 1=1 ";

            if (!empty($month)) {
                $safeMonth = $conn->real_escape_string($month);
                $sql .= " AND DATE_FORMAT(r.timestamp, '%Y-%m') = '$safeMonth' ";
            }

            $sql .= $fleetFilter . $unitFilter;
            $sql .= $groupByClause;

            $result = $conn->query($sql);
            $data = [];
            $totalKm = 0;
            $totalLts = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $km = floatval($row['sum_km']);
                    $lts = floatval($row['sum_lts']);
                    $rendimiento = ($lts > 0) ? ($km / $lts) : 0;
                    
                    $totalKm += $km;
                    $totalLts += $lts;

                    // Solo enviamos los que sí tuvieron actividad
                    if ($rendimiento > 0) {
                        $data[] = [
                            'label' => $row['label'],
                            'rendimiento' => round($rendimiento, 2)
                        ];
                    }
                }
            }
            
            // Ordenar de mayor a menor rendimiento
            usort($data, function($a, $b) { return $b['rendimiento'] <=> $a['rendimiento']; });
            
            // Promedio global exacto del total seleccionado
            $promedioGlobal = ($totalLts > 0) ? ($totalKm / $totalLts) : 0;
            
            echo json_encode(['data' => $data, 'promedio' => round($promedioGlobal, 2)]);
            exit;

        default:
            $response = []; 
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(200); 
    echo json_encode(['error' => $e->getMessage(), 'data' => []]);
}

if (isset($conn)) $conn->close();
?>