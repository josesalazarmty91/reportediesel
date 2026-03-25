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
//CONETA

    switch ($action) {
        case 'getMonthlyStats':
            // 1. INTENTO PRINCIPAL: Unir con la base de datos externa (grupoam6_diesel)
            // Esta query suma litros_diesel + litros_auto de la tabla externa
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

            // 2. PLAN B (RESPALDO): Si la query de arriba falla (Error 500), usamos solo datos locales
            if (!$result) {
                // Logueamos el error internamente pero no rompemos el JSON
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
                    $response[] = $row; // Llenamos el array con lo que haya salido
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
            
            // Devolvemos estructura especial, salimos aquí.
            echo json_encode(['data' => $days, 'total_acumulado' => $totalAcumulado]);
            exit; 
            
            // --- AGREGAR ESTE NUEVO CASE ---
        case 'getTabletStats':
            // Recibimos el mes del filtro
            $month = $_GET['month'] ?? ''; // Formato YYYY-MM

            // Consulta directa a la BD de diesel
            // Sumamos litros_diesel + litros_auto (Vales)
            // Agrupamos por día para ver la evolución dentro del mes seleccionado
           // Código NUEVO (Solo Diesel)
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
            
            // Devolvemos los datos diarios y el gran total del mes
            echo json_encode(['data' => $days, 'total_acumulado' => $totalAcumulado]);
            exit;

            // --- OBTENER LISTA DE UNIDADES DEL MES (Para el filtro checkbox) ---
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

        // --- OBTENER ESTADÍSTICAS DE KM POR UNIDAD ---
        case 'getKmStats':
            $month = $_GET['month'] ?? '';
            $unitsStr = $_GET['units'] ?? ''; // Recibiremos unidades separadas por coma
            
            // Limpiamos la cadena de unidades para usarla en IN (...)
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
                        unit_number,
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

            $sql .= " GROUP BY unit_number ORDER BY total_km DESC"; // Ordenamos de mayor a menor KM

            $result = $conn->query($sql);
            $data = [];
            $totalMes = 0;

            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $val = floatval($row['total_km']);
                    $totalMes += $val;
                    $data[] = [
                        'unit' => $row['unit_number'],
                        'km' => $val
                    ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;

// --- NUEVO: Obtener unidades para el filtro de Consumo Diesel (Tableta) ---
        case 'getDieselUnitsByMonth':
            $month = $_GET['month'] ?? '';
            // Consultamos la BD externa y unimos con units para tener el nombre
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

        // --- NUEVO: Datos para Gráfica Consumo Diesel por Unidad ---
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
                    // Filtramos usando el alias 'u'
                    $unitFilter = " AND u.unit_number IN (" . implode(',', $cleanUnits) . ") ";
                }
            }

            // Sumamos Diesel + Auto (Vales)
            $sql = "SELECT 
                        u.unit_number,
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
                        'unit' => $row['unit_number'],
                        'fuel' => $val
                    ];
                }
            }
            
            echo json_encode(['data' => $data, 'total_acumulado' => $totalMes]);
            exit;




        default:
            // Si no hay acción, devolvemos array vacío en vez de error
            $response = []; 
    }

    echo json_encode($response);

} catch (Exception $e) {
    // En caso de error fatal, devolvemos un JSON con el error para que JS no truene con "Unexpected token"
    http_response_code(200); // Enviamos 200 para que JS lea el mensaje
    echo json_encode(['error' => $e->getMessage(), 'data' => []]);
}

if (isset($conn)) $conn->close();
?>