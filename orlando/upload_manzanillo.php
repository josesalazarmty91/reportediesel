<?php
// upload_manzanillo.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

require 'db_config.php';

$response = ['status' => 'error', 'message' => '', 'inserted' => 0, 'skipped' => 0];

try {
    // 1. Recibir los datos JSON enviados desde el navegador
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data']) || !is_array($input['data'])) {
        throw new Exception("No se recibieron datos válidos.");
    }

    $rows = $input['data']; // Array de filas del Excel
    $fileName = $input['fileName'] ?? 'Carga Manual';

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión BD");
    $conn->set_charset('utf8mb4');

    // Preparar consultas
    $stmtCheck = $conn->prepare("SELECT id FROM registros_manzanillo WHERE unidad = ? AND fecha_carga = ? AND hora_carga = ? LIMIT 1");
    $stmtInsert = $conn->prepare("INSERT INTO registros_manzanillo (unidad, fecha_carga, hora_carga, litros_diesel, nombre_archivo_origen) VALUES (?, ?, ?, ?, ?)");

    $insertedCount = 0;
    $skippedCount = 0;

    // 2. Procesar cada fila
    foreach ($rows as $index => $row) {
        // Ignorar encabezados (primera fila o si la celda 'Despachado' no es numérica)
        if ($index === 0 || !is_numeric($row[1] ?? '')) continue;

        // Mapeo según tu archivo 'modelodata.xls':
        // Col 0: Ticket
        // Col 1: Despachado (Fecha Excel Serial ej: 45992.78...)
        // Col 2: NoEco (Unidad)
        // Col 3: Volumen (Litros)

        $rawExcelDate = $row[1] ?? 0;
        $unidad = trim($row[2] ?? '');
        $litros = floatval($row[3] ?? 0);

        if (empty($unidad) || empty($rawExcelDate)) continue;

        // CONVERSIÓN FECHA EXCEL -> REAL
        // 25569 = Días entre 1900 y 1970
        // 86400 = Segundos en un día
        $unixDate = ($rawExcelDate - 25569) * 86400;
        
        // Ajuste GMT/UTC (Excel serial suele ser local, pero al pasar a UNIX puede requerir ajuste según servidor)
        // Usamos gmdate para evitar desplazamientos raros si el servidor tiene otra zona
        $fechaCarga = gmdate("d/m/Y", $unixDate);
        $horaCarga = gmdate("H:i:s", $unixDate);

        // 3. Validación y Guardado
        $stmtCheck->bind_param("sss", $unidad, $fechaCarga, $horaCarga);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $skippedCount++;
        } else {
            $stmtInsert->bind_param("sssds", $unidad, $fechaCarga, $horaCarga, $litros, $fileName);
            if ($stmtInsert->execute()) {
                $insertedCount++;
            }
        }
    }

    $stmtCheck->close();
    $stmtInsert->close();
    $conn->close();

    $response['status'] = 'success';
    $response['message'] = "Proceso completado.";
    $response['inserted'] = $insertedCount;
    $response['skipped'] = $skippedCount;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>