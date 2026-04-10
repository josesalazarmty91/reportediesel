<?php
// save_toma.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';

$response = ['status' => 'error', 'message' => 'Error desconocido'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // Obtener datos
    $no_muestra = $_POST['no_muestra'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $litros = $_POST['litros'] ?? 0;

    // Validaciones básicas
    if (empty($no_muestra) || empty($fecha) || empty($hora)) {
        throw new Exception("Por favor completa todos los campos.");
    }

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error BD: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    // Insertar
    $sql = "INSERT INTO registro_tomas (no_muestra, fecha, hora, litros) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssd", $no_muestra, $fecha, $hora, $litros);

    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Toma registrada correctamente.'];
    } else {
        throw new Exception("Error al guardar: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>