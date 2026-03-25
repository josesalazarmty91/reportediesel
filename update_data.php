<?php
// update_data.php
// Controlador central para actualizaciones de datos
header('Content-Type: application/json');
ini_set('display_errors', 0);
require 'db_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$response = ['status' => 'error', 'message' => 'Acción no válida o datos faltantes'];

try {
    if (!isset($input['action'])) {
        throw new Exception("No se especificó la acción.");
    }

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) throw new Exception("Error conexión BD");
    $conn->set_charset('utf8mb4');

    switch ($input['action']) {
        // --- CASO 1: ACTUALIZAR REPORTE DIESEL (ECM) ---
        case 'update_diesel':
            if (isset($input['id'], $input['unit'], $input['hubo'], $input['km'], $input['litros'])) {
                
                // 1. Limpieza de datos (NUEVO: quitamos comas para evitar errores numéricos)
                $hubo = str_replace(',', '', $input['hubo']);
                $km = str_replace(',', '', $input['km']);
                $litros = str_replace(',', '', $input['litros']);
                $unit = trim($input['unit']);

                $sql = "UPDATE trip_reports 
                        SET unit_number = ?, 
                            km_hubodometro = ?, 
                            km_recorrido = ?, 
                            combustible_viaje = ? 
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Error en query Diesel: " . $conn->error);
                
                $stmt->bind_param("ssssi", $unit, $hubo, $km, $litros, $input['id']);
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Reporte ECM actualizado'];
                } else {
                    throw new Exception("Error al guardar ECM: " . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Faltan datos para ECM");
            }
            break;

        // --- CASO 2: ACTUALIZAR REPORTE TABLETA ---
        case 'update_tablet':
            if (isset($input['id'], $input['bitacora'], $input['km_ini'], $input['km_fin'], $input['diesel'], $input['vales'], $input['urea'], $input['totalizador'])) {
                
                // 1. Limpieza de Kilómetros
                $kIni = floatval(str_replace(',', '', $input['km_ini']));
                $kFin = floatval(str_replace(',', '', $input['km_fin']));
                
                // 2. Cálculo automático
                $kmRecorrido = $kFin - $kIni;
                if($kmRecorrido < 0) $kmRecorrido = 0; 

                // 3. Limpieza de Litros (ESTAS SON LAS LÍNEAS QUE FALTABAN)
                $dieselVal = str_replace(',', '', $input['diesel']);
                $valesVal = str_replace(',', '', $input['vales']);
                $ureaVal = str_replace(',', '', $input['urea']);
                $totalizadorVal = str_replace(',', '', $input['totalizador']);

                $sql = "UPDATE grupoam6_diesel.registros_entrada 
                        SET bitacora_number = ?, 
                            km_inicio = ?, 
                            km_fin = ?, 
                            km_recorridos = ?, 
                            litros_diesel = ?, 
                            litros_auto = ?, 
                            litros_urea = ?,
                            litros_totalizador = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Error en query Tableta: " . $conn->error);

                $stmt->bind_param("ssssssssi", 
                    $input['bitacora'], 
                    $input['km_ini'],     // Guardamos tal cual escribió (limpio en JS o DB lo maneja) o mejor usamos $kIni si quieres forzar numero
                    $input['km_fin'], 
                    $kmRecorrido,         // Calculado
                    $dieselVal,           // Limpio
                    $valesVal,            // Limpio
                    $ureaVal,             // Limpio
                    $totalizadorVal,      // Limpio
                    $input['id']
                );

                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Reporte Tableta actualizado'];
                } else {
                    throw new Exception("Error al guardar Tableta: " . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception("Faltan datos para Tableta");
            }
            break;

        default:
            throw new Exception("Acción desconocida");
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(200);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>