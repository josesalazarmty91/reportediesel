<?php
// save_entrada_almacen.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';

$response = ['status' => 'error', 'message' => 'Error desconocido'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // Obtener datos
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $base = $_POST['base'] ?? 'García';
    $folio = $_POST['folio'] ?? '';
    $proveedor = $_POST['proveedor'] ?? '';
    $litros_factura = $_POST['litros'] ?? 0;
    $litros_reales = $_POST['litros_reales'] ?? 0;

    // Validaciones básicas
    if (empty($fecha) || empty($hora) || empty($folio)) {
        throw new Exception("Por favor completa Fecha, Hora y Folio.");
    }

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error BD: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    // Insertar
    $sql = "INSERT INTO entradas_almacen (fecha, hora, base, folio, proveedor, litros_factura, litros_reales) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssdd", $fecha, $hora, $base, $folio, $proveedor, $litros_factura, $litros_reales);

    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Entrada registrada correctamente.'];
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