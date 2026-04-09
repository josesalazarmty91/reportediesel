<?php
// upload_diesel_entry.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'db_config.php';

$response = [];

try {
    // 1. Validar Inputs
    if (!isset($_FILES['entryFile']) || $_FILES['entryFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No se recibió ningún archivo válido.");
    }
    
    if (!isset($_POST['base']) || empty($_POST['base'])) {
        throw new Exception("Debes seleccionar una Base.");
    }

    $baseSelected = $_POST['base'];
    $tmpPath = $_FILES['entryFile']['tmp_name'];
    $fileName = $_FILES['entryFile']['name'];
    $fileType = pathinfo($fileName, PATHINFO_EXTENSION);

    if (strtolower($fileType) !== 'xml') {
        throw new Exception("Por favor sube un archivo .XML válido.");
    }

    // 2. Cargar XML
    $xmlContent = file_get_contents($tmpPath);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) throw new Exception("El archivo XML está dañado.");

    $ns = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('cfdi', $ns['cfdi']);
    $xml->registerXPathNamespace('tfd', $ns['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // 3. Extraer Datos Generales
    $rawDate = (string)$xml['Fecha']; 
    $fechaFactura = date('Y-m-d', strtotime($rawDate));

    $serie = (string)$xml['Serie'];
    $folio = (string)$xml['Folio'];
    $folioCompleto = trim($serie . ' ' . $folio);

    $subTotal = (float)$xml['SubTotal'];
    $totalFactura = (float)$xml['Total'];

    // PROVEEDOR (Emisor)
    $emisor = $xml->xpath('//cfdi:Emisor');
    $nombreProveedor = (string)$emisor[0]['Nombre'];
    if (empty($nombreProveedor)) $nombreProveedor = (string)$emisor[0]['Rfc'];

    // --- NUEVO: RECEPTOR (Empresa que recibe) ---
    $receptor = $xml->xpath('//cfdi:Receptor');
    $rfcReceptor = (string)$receptor[0]['Rfc'];
    $nombreReceptor = (string)$receptor[0]['Nombre'];
    // ---------------------------------------------

    $timbre = $xml->xpath('//tfd:TimbreFiscalDigital');
    $uuid = (string)$timbre[0]['UUID'];

    // 4. Buscar Concepto Diesel
    $litros = 0;
    $precioUnitario = 0;
    
    $conceptos = $xml->xpath('//cfdi:Concepto');
    foreach ($conceptos as $c) {
        $clave = (string)$c['ClaveProdServ'];
        if ($clave === '15101505' || stripos((string)$c['Descripcion'], 'DIESEL') !== false) {
            $litros += (float)$c['Cantidad'];
            if ($precioUnitario == 0) $precioUnitario = (float)$c['ValorUnitario'];
        }
    }
    if ($litros == 0 && count($conceptos) > 0) {
        $litros = (float)$conceptos[0]['Cantidad'];
        $precioUnitario = (float)$conceptos[0]['ValorUnitario'];
    }

    // 5. Insertar en BD
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error BD: " . $conn->connect_error);

    // Verificar duplicados
    $check = $conn->prepare("SELECT id FROM entrada_diesel WHERE uuid = ?");
    $check->bind_param("s", $uuid);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) throw new Exception("⛔ Esta factura ya existe en el sistema.");
    $check->close();

    // Insertar (Incluyendo RFC y Nombre Receptor)
    $sql = "INSERT INTO entrada_diesel (uuid, base, fecha_factura, folio, proveedor, receptor_rfc, receptor_nombre, litros, precio_unitario, subtotal, importe_total, nombre_archivo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    // Tipos: s=string, d=double. Total 12 variables.
    $stmt->bind_param("sssssssdddds", $uuid, $baseSelected, $fechaFactura, $folioCompleto, $nombreProveedor, $rfcReceptor, $nombreReceptor, $litros, $precioUnitario, $subTotal, $totalFactura, $fileName);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = "Carga exitosa.\nFolio: $folioCompleto\nReceptor: $nombreReceptor";
    } else {
        throw new Exception("Error al guardar: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>