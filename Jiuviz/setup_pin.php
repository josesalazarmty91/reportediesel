<?php
// setup_pin.php
require 'db_config.php'; // Tu archivo de conexión existente

// Crear conexión
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// 1. Crear tabla simple de configuración si no existe
$sqlTable = "CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY,
    access_pin_hash VARCHAR(255) NOT NULL
)";
$conn->query($sqlTable);

// 2. Definir el PIN (Cámbialo aquí si quieres otro)
$miPin = "1234"; 

// 3. Encriptar el PIN
$hash = password_hash($miPin, PASSWORD_DEFAULT);

// 4. Guardar en la base de datos (ID siempre será 1)
$sqlInsert = "REPLACE INTO system_config (id, access_pin_hash) VALUES (1, '$hash')";

if ($conn->query($sqlInsert) === TRUE) {
    echo "<h1>¡Listo!</h1>";
    echo "<p>Sistema configurado con PIN de acceso: <strong>$miPin</strong></p>";
    echo "<p>Ya puedes borrar este archivo y usar el sistema.</p>";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>