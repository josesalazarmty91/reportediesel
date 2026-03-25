<?php
// almacen.php
// Controlador único para el módulo de Almacén (GET = Leer, POST = Guardar)
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';
ini_set('display_errors', 0);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$response = [];

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión BD: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    // --- CASO 1: GUARDAR (POST) ---
    if ($method === 'POST') {
        // Validar datos básicos
        if (empty($_POST['fecha']) || empty($_POST['folio'])) {
            throw new Exception("Faltan datos obligatorios (Fecha o Folio).");
        }

        $sql = "INSERT INTO entradas_almacen (fecha, hora, base, folio, proveedor, litros_factura, litros_reales) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Error en query: " . $conn->error);

        // Convertir a float para seguridad
        $l_factura = floatval($_POST['litros_factura'] ?? 0);
        $l_reales = floatval($_POST['litros_reales'] ?? 0);

        $stmt->bind_param("sssssdd", 
            $_POST['fecha'], 
            $_POST['hora'], 
            $_POST['base'], 
            $_POST['folio'], 
            $_POST['proveedor'], 
            $l_factura, 
            $l_reales
        );

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Entrada registrada correctamente.'];
        } else {
            throw new Exception("Error al guardar: " . $stmt->error);
        }
        $stmt->close();
    } 
    
    // --- CASO 2: LEER (GET) ---
    else {
        // Traemos los últimos 100 registros para la tabla
        $sql = "SELECT * FROM entradas_almacen ORDER BY fecha DESC, hora DESC LIMIT 100";
        $result = $conn->query($sql);
        
        $data = [];
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        // En GET devolvemos directamente el array de datos
        echo json_encode($data);
        exit; 
    }

    $conn->close();

} catch (Exception $e) {
    // Si es POST devolvemos estructura de error, si es GET devolvemos array vacío
    if ($method === 'POST') {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    } else {
        $response = []; 
    }
}

// Imprimir respuesta para POST
if ($method === 'POST') {
    echo json_encode($response);
}
?>