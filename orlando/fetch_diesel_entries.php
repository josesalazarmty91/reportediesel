<?php
// fetch_diesel_entries.php
header('Content-Type: application/json');
require 'db_config.php';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$data = [];

if (!$conn->connect_error) {
    // 1. Recibir parámetros (Filtros)
    $month = $_GET['month'] ?? date('Y-m'); // Por defecto: Mes Actual
    $folio = $_GET['folio'] ?? '';
    $proveedor = $_GET['proveedor'] ?? '';
    $base = $_GET['base'] ?? '';

    // 2. Construir Query Dinámico
    $sql = "SELECT * FROM entrada_diesel WHERE 1=1";
    $types = "";
    $params = [];

    // Filtro de Mes
    if (!empty($month)) {
        $sql .= " AND DATE_FORMAT(fecha_factura, '%Y-%m') = ?";
        $types .= "s";
        $params[] = $month;
    }

    // Filtro de Folio (Búsqueda parcial)
    if (!empty($folio)) {
        $sql .= " AND folio LIKE ?";
        $types .= "s";
        $params[] = "%$folio%";
    }

    // Filtro de Proveedor (Búsqueda parcial)
    if (!empty($proveedor)) {
        $sql .= " AND proveedor LIKE ?";
        $types .= "s";
        $params[] = "%$proveedor%";
    }

    // Filtro de Base (Exacto)
    if (!empty($base)) {
        $sql .= " AND base = ?";
        $types .= "s";
        $params[] = $base;
    }

    $sql .= " ORDER BY fecha_factura DESC";

    // 3. Ejecutar consulta preparada
    $stmt = $conn->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $conn->close();
}

echo json_encode($data);
?>