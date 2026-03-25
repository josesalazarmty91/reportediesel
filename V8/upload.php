<?php
// Desactivar errores en la salida final y establecer cabecera JSON
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

$response = [];

// --- FUNCIÓN DE AYUDA PARA FECHAS ---
function parseAndFormatDateTime($dateString, $inputFormat) {
    $date = 'N/D';
    $time = 'N/D';
    $dateString = trim($dateString);
    $dateString = preg_replace('/\s+/', ' ', $dateString);
    $dateStringAmPm = str_replace(['a. m.', 'p. m.'], ['AM', 'PM'], $dateString);
    $stringToParse = (strpos($inputFormat, 'A') !== false) ? $dateStringAmPm : $dateString;
    $dateTime = DateTime::createFromFormat($inputFormat, $stringToParse);
    
    if ($dateTime === false) {
        $altFormat = '';
        if (strpos($inputFormat, 'h:i:s A') !== false) {
            $altFormat = str_replace('h:i:s A', 'H:i:s', $inputFormat);
            $stringToParse = $dateString; 
        } elseif (strpos($inputFormat, 'H:i:s') !== false) {
            $altFormat = str_replace('H:i:s', 'h:i:s A', $inputFormat);
            $stringToParse = $dateStringAmPm; 
        }
        if ($altFormat) {
            $dateTime = DateTime::createFromFormat($altFormat, $stringToParse);
        }
    }
    if ($dateTime) {
        $date = $dateTime->format('m/d/Y'); 
        $time = $dateTime->format('H:i:s'); 
    }
    return ['date' => $date, 'time' => $time];
}

try {
    // --- 1. CONFIGURACIÓN ---
    $configFile = __DIR__ . '/db_config.php';
    if (!file_exists($configFile) || !is_readable($configFile)) throw new Exception("Error config BD.", 1);
    require $configFile;
    
    if (!isset($DB_HOST) || !isset($DB_USER) || !isset($DB_PASS) || !isset($DB_NAME)) {
        throw new Exception("Variables de configuración BD no definidas.", 2);
    }

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión BD: " . $conn->connect_error, 3);
    $conn->set_charset('utf8mb4');

    // --- 2. VALIDACIÓN ARCHIVO ---
    if (!isset($_FILES['xmlFile']) || $_FILES['xmlFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir archivo.');
    }
    $xmlFilePath = $_FILES['xmlFile']['tmp_name'];
    $fileName = basename($_FILES['xmlFile']['name']);
    $xmlContent = file_get_contents($xmlFilePath);
    if ($xmlContent === false) throw new Exception('No se pudo leer XML.');

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        libxml_clear_errors();
        throw new Exception('XML mal formado.');
    }

    // --- 3. EXTRACCIÓN DATOS ---
    $reportData = [];
    $unitNumber = 'N/D';

    // Mapa de Columnas BD
    $dbColumnMap = [
        "KM Recorrido" => "km_recorrido",
        "Distancia conducida" => "distancia_conducida",
        "Distancia en top gear" => "distancia_top_gear",
        "Distancia en cambio bajo" => "distancia_cambio_bajo",
        "Combustible del viaje" => "combustible_viaje",
        "Combustible manejando" => "combustible_manejando",
        "Combustible en ralentí" => "combustible_ralenti",
        "DEF usado" => "def_usado",
        "Tiempo del viaje" => "tiempo_viaje",
        "Tiempo manejando" => "tiempo_manejando",
        "Tiempo en ralentí" => "tiempo_ralenti",
        "Tiempo en top gear" => "tiempo_top_gear",
        "Tiempo en crucero" => "tiempo_crucero",
        "Tiempo en exceso de velocidad" => "tiempo_exceso_velocidad",
        "Velocidad máxima" => "velocidad_maxima",
        "RPM máxima" => "rpm_maxima",
        "Velocidad promedio" => "velocidad_promedio",
        "Rendimiento del viaje" => "rendimiento_viaje",
        "Rendimiento manejando" => "rendimiento_manejando",
        "Factor de carga" => "factor_carga",
        "Eventos de exceso de velocidad" => "eventos_exceso_velocidad",
        "Eventos de frenado" => "eventos_frenado",
        "Tiempo en neutro/coasting" => "tiempo_neutro_coasting",
        "Tiempo en PTO" => "tiempo_pto",
        "Combustible PTO" => "combustible_pto",
        "KM HUBODOMETRO" => "km_hubodometro",
        "Travesia KM" => "travesia_km" // <--- NUEVA COLUMNA
    ];
    
    // Mapeo XML
    $mapping = [
        "KM Recorrido" => ["Cummins" => "Trip Distance", "Detroit" => "Trip Distance"],
        "Distancia conducida" => ["Cummins" => "Drive Distance", "Detroit" => "Drive Distance"],
        "Distancia en top gear" => ["Cummins" => "Top Gear Distance", "Detroit" => "Top Gear Distance"],
        "Distancia en cambio bajo" => ["Cummins" => "Gear Down Distance", "Detroit" => "Top Gear -1 Distance"],
        "Combustible del viaje" => ["Cummins" => "Trip Fuel Used", "Detroit" => "Trip Fuel"],
        "Combustible manejando" => ["Cummins" => "Drive Fuel Used", "Detroit" => "Drive Fuel"],
        "Combustible en ralentí" => ["Cummins" => "Idle Fuel Used", "Detroit" => "Idle Fuel"],
        "DEF usado" => ["Cummins" => "Trip Diesel Exhaust Fluid Used", "Detroit" => "Trip Def H / Def Fuel"],
        "Tiempo del viaje" => ["Cummins" => "Trip Time", "Detroit" => "Trip Time"],
        "Tiempo manejando" => ["Cummins" => "Trip Drive Time", "Detroit" => "Drive Time"],
        "Tiempo en ralentí" => ["Cummins" => "Trip Idle Time", "Detroit" => "Idle Time"],
        "Tiempo en top gear" => ["Cummins" => "Trip Top Gear Time", "Detroit" => "Top Gear Time"],
        "Tiempo en crucero" => ["Cummins" => "Trip Cruise Time", "Detroit" => "Cruise Time"],
        "Tiempo en exceso de velocidad" => ["Cummins" => "Overspeed 1/2 Time", "Detroit" => "Over Speed A/B Time"],
        "Velocidad máxima" => ["Cummins" => "Maximum Vehicle Speed", "Detroit" => "Peak Road Speed"],
        "RPM máxima" => ["Cummins" => "Maximum Engine Speed", "Detroit" => "Peak Engine RPM"],
        "Velocidad promedio" => ["Cummins" => "Average Vehicle Speed", "Detroit" => "Avg Vehicle Speed"],
        "Rendimiento del viaje" => ["Cummins" => "Trip Average Fuel Economy", "Detroit" => "Trip Economy"],
        "Rendimiento manejando" => ["Cummins" => "Drive Average Fuel Economy", "Detroit" => "Driving Economy"],
        "Factor de carga" => ["Cummins" => "Average Engine Load", "Detroit" => "Drive Average Load Factor"],
        "Eventos de exceso de velocidad" => ["Cummins" => "Overspeed Events", "Detroit" => "Over Speed A/B Count"],
        "Eventos de frenado" => ["Cummins" => "Sudden Deceleration Counts", "Detroit" => "Brake Count / Firm brake count"],
        "Tiempo en neutro/coasting" => ["Cummins" => "Coast Time", "Detroit" => "Coast Time"],
        "Tiempo en PTO" => ["Cummins" => "Total PTO Time", "Detroit" => "VSG (PTO) Time"],
        "Combustible PTO" => ["Cummins" => "Total PTO Fuel Used", "Detroit" => "VSG (PTO) Fuel"],
        "KM HUBODOMETRO" => ["Cummins" => "Total Engine Distance", "Detroit" => "Total Distance"],
        // AQUI ESTA LA NUEVA COLUMNA (Mismo mapeo que Hubodómetro según indicación)
        "Travesia KM" => ["Cummins" => "Total Engine Distance", "Detroit" => "Total Distance"]
    ];

    if (isset($xml->TripInfoParameters)) {
        // --- CUMMINS (TripInfo) ---
        $unitNumber = (string) $xml->DeviceInfo['UnitNumber'];
        $rawDateStr = (string) $xml->DeviceInfo['ReportDate'];
        $parsedDateTime = parseAndFormatDateTime($rawDateStr, 'd/m/Y h:i:s A');
        $reportData['report_date'] = $parsedDateTime['date'];
        $reportData['report_time'] = $parsedDateTime['time'];

        foreach ($mapping as $nombreReporte => $tags) {
            $tagName = $tags["Cummins"];
            $value = 'N/D';
            
            if ($tagName === "Overspeed 1/2 Time") {
                $time1 = (float) $xml->TripInfoParameters->xpath("//TripInfo[@Name='Overspeed 1 Time']/@Value")[0];
                $time2 = (float) $xml->TripInfoParameters->xpath("//TripInfo[@Name='Overspeed 2 Time']/@Value")[0];
                $value = $time1 + $time2;
            } else {
                $nodes = $xml->TripInfoParameters->xpath("//TripInfo[@Name='{$tagName}']/@Value");
                if (!empty($nodes)) $value = (string) $nodes[0];
            }
            $reportData[$dbColumnMap[$nombreReporte]] = $value;
        }

    } elseif (isset($xml->DataFile->TripActivity)) {
        // --- DETROIT (Parameter) ---
        $unitNumber = (string) $xml->DataFile['VehicleID'];
        $rawDateStr = (string) $xml->DataFile['PC_Date'];
        $parsedDateTime = parseAndFormatDateTime($rawDateStr, 'm/d/Y H:i:s');
        $reportData['report_date'] = $parsedDateTime['date'];
        $reportData['report_time'] = $parsedDateTime['time'];

        foreach ($mapping as $nombreReporte => $tags) {
            $tagName = $tags["Detroit"];
            $value = 'N/D';

            if ($tagName === "Over Speed A/B Time") {
                $timeA = (float) $xml->DataFile->TripActivity->xpath("//Parameter[@Name='Over Speed A Time']")[0];
                $timeB = (float) $xml->DataFile->TripActivity->xpath("//Parameter[@Name='Over Speed B Time']")[0];
                $value = $timeA + $timeB;
            } elseif (strpos($tagName, " / ") !== false) {
                $parts = explode(" / ", $tagName);
                $nodes1 = $xml->DataFile->TripActivity->xpath("//Parameter[@Name='{$parts[0]}']");
                $nodes2 = $xml->DataFile->TripActivity->xpath("//Parameter[@Name='{$parts[1]}']");
                $val1 = !empty($nodes1) ? (int)$nodes1[0] : 0;
                $val2 = !empty($nodes2) ? (int)$nodes2[0] : 0;
                $value = $val1 + $val2;
            } elseif ($tagName === "Trip Def H / Def Fuel") {
                $nodes1 = $xml->DataFile->TripActivity->xpath("//Parameter[@Name='Trip Def H']");
                $nodes2 = $xml->DataFile->TripActivity->xpath("//Parameter[@Name='Def Fuel']");
                $val1 = !empty($nodes1) ? (float)$nodes1[0] : 0;
                $val2 = !empty($nodes2) ? (float)$nodes2[0] : 0;
                $value = $val1 + $val2;
            } else {
                $nodes = $xml->DataFile->TripActivity->xpath("//Parameter[@Name='{$tagName}']");
                if (!empty($nodes)) $value = (string) $nodes[0];
            }
            $reportData[$dbColumnMap[$nombreReporte]] = $value;
        }
    } else {
        throw new Exception('Formato de XML no reconocido.');
    }

    // --- 4. LIMPIEZA DE DATOS (UNIDAD) Y CANDADOS ---
    $cleanedUnitNumber = str_replace('#', '', $unitNumber);
    $cleanedUnitNumber = trim($cleanedUnitNumber);

    // [INICIO NUEVO] Regla para estandarizar unidades menores a 100 (93 -> 093)
    if (is_numeric($cleanedUnitNumber) && strlen($cleanedUnitNumber) < 3) {
        // Rellena con ceros a la izquierda hasta completar 3 dígitos
        // Ej: "12" -> "012", "93" -> "093", "5" -> "005"
        $cleanedUnitNumber = str_pad($cleanedUnitNumber, 3, '0', STR_PAD_LEFT);
    }
    // [FIN NUEVO]

 

    if (empty($cleanedUnitNumber) || $cleanedUnitNumber === 'N/D') {
        $cleanedUnitNumber = 'PENDIENTE';
    }

    // Candado 1: Fechas Futuras
    if ($reportData['report_date'] !== 'N/D') {
        $reportDateObj = DateTime::createFromFormat('m/d/Y', $reportData['report_date']);
        $now = new DateTime();
        $futureLimit = (clone $now)->modify('+1 day');
        if ($reportDateObj && $reportDateObj > $futureLimit) {
            throw new Exception("ERROR DE SEGURIDAD: La fecha del reporte (" . $reportData['report_date'] . ") está en el futuro.");
        }
    }

    // Candado 2: Duplicados
    $dupSql = "SELECT id FROM trip_reports WHERE unit_number = ? AND report_date = ? AND report_time = ? LIMIT 1";
    $dupStmt = $conn->prepare($dupSql);
    if ($dupStmt) {
        $dupStmt->bind_param("sss", $cleanedUnitNumber, $reportData['report_date'], $reportData['report_time']);
        $dupStmt->execute();
        $dupStmt->store_result();
        if ($dupStmt->num_rows > 0) {
            $dupStmt->close();
            throw new Exception("DUPLICADO DETECTADO: Ya existe este reporte.");
        }
        $dupStmt->close();
    }

    // Candado 3: Existencia Unidad
    if ($cleanedUnitNumber !== 'PENDIENTE') {
        $unitCheckSql = "SELECT id FROM grupoam6_diesel.units WHERE unit_number = ? LIMIT 1";
        $unitCheckStmt = $conn->prepare($unitCheckSql);
        if ($unitCheckStmt) {
            $unitCheckStmt->bind_param("s", $cleanedUnitNumber);
            $unitCheckStmt->execute();
            $unitCheckStmt->store_result();
            if ($unitCheckStmt->num_rows === 0) {
                $unitCheckStmt->close();
                throw new Exception("UNIDAD DESCONOCIDA: La unidad '$cleanedUnitNumber' no está registrada.");
            }
            $unitCheckStmt->close();
        }
    }

    // --- 5. INSERCIÓN EN BASE DE DATOS ---
    $reportData['file_name'] = $fileName;
    $reportData['unit_number'] = $cleanedUnitNumber;

    $columns = implode(", ", array_keys($reportData));
    $placeholders = implode(", ", array_fill(0, count($reportData), "?"));
    $types = str_repeat("s", count($reportData));
    $values = array_values($reportData);

    $sql = "INSERT INTO trip_reports ($columns) VALUES ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error DB Insert (Verifica columna travesia_km): " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la inserción: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // --- 6. RESPUESTA DE ÉXITO ---
    $response['status'] = 'success';
    $msgUnidad = ($cleanedUnitNumber === 'PENDIENTE') ? 'PENDIENTE DE ASIGNAR' : $cleanedUnitNumber;
    $response['message'] = 'Éxito: Reporte cargado. Unidad: ' . $msgUnidad . ' (' . $reportData['report_date'] . ').';
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500); 
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>