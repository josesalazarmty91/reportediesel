<?php
// fetch_tomas.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$data = [];

if (!$conn->connect_error) {
    $conn->set_charset("utf8mb4");
    // Traemos los últimos 50 registros
    $sql = "SELECT * FROM registro_tomas ORDER BY fecha DESC, hora DESC LIMIT 50";
    $result = $conn->query($sql);
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $conn->close();
}

echo json_encode($data);
?>