<?php
// verify_pin.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
require 'db_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$pin = $input['pin'] ?? '';

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión");

    // Buscamos el hash del PIN (ID siempre es 1 según setup_pin.php)
    $sql = "SELECT access_pin_hash FROM system_config WHERE id = 1";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        // Verificamos si el PIN ingresado coincide con el encriptado
        if (password_verify($pin, $row['access_pin_hash'])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'NIP Incorrecto']);
        }
    } else {
        // Si no se ha configurado el PIN aún
        echo json_encode(['status' => 'error', 'message' => 'Sistema no configurado (Ejecuta setup_pin.php)']);
    }
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de sistema']);
}
?>