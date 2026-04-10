<?php
// update_unit.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

$response = [];

try {
    require __DIR__ . '/db_config.php';

    // Obtener datos del JSON enviado
    $input = json_decode(file_get_contents('php://input'), true);
    $reportId = $input['id'] ?? null;
    $newUnit = $input['unit'] ?? null;

    if (!$reportId || !$newUnit) {
        throw new Exception("Faltan datos (ID o Unidad).");
    }

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error)
        throw new Exception("Error conexión BD.");
    $conn->set_charset('utf8mb4');

    // 1. VALIDAR QUE LA NUEVA UNIDAD EXISTA REALMENTE (Candado de Seguridad)
    // Asumimos acceso a grupoam6_diesel
    $checkSql = "SELECT id FROM grupoam6_diesel_jiuviz.units WHERE unit_number = ? LIMIT 1";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("s", $newUnit);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows === 0) {
        throw new Exception("La unidad '$newUnit' no existe en el catálogo maestro.");
    }
    $stmtCheck->close();

    // 2. ACTUALIZAR EL REPORTE
    $updateSql = "UPDATE trip_reports SET unit_number = ? WHERE id = ?";
    $stmtUpdate = $conn->prepare($updateSql);
    $stmtUpdate->bind_param("si", $newUnit, $reportId);

    if ($stmtUpdate->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Unidad actualizada correctamente.';
    }
    else {
        throw new Exception("Error al actualizar: " . $conn->error);
    }

    $stmtUpdate->close();
    $conn->close();

}
catch (Exception $e) {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>