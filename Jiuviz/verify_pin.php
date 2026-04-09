<?php
// verify_pin.php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pin = $input['pin'] ?? '';

// Definición de roles y sus NIPs
// 1234 = Administrador (Edita, Sube archivos, Ve todo)
// 9823 = Visualizador (Solo ve reportes y gráficas)

if ($pin === '1234') {
    echo json_encode(['status' => 'success', 'role' => 'admin']);
} elseif ($pin === '9823') {
    echo json_encode(['status' => 'success', 'role' => 'viewer']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'NIP Incorrecto']);
}
?>