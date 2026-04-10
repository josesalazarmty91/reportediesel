<?php
// reportes_jiuviz/fetch_transacciones_diarias.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';
ini_set('display_errors', 0);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error)
        throw new Exception("Error conexión BD: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $inicio_dia = $fecha . ' 00:00:00';

    $sql_toma_previa = "SELECT litros, CONCAT(fecha, ' ', hora) as fecha_hora 
                        FROM registro_tomas 
                        WHERE CONCAT(fecha, ' ', hora) < '$inicio_dia' 
                        ORDER BY fecha DESC, hora DESC LIMIT 1";
    $res_toma = $conn->query($sql_toma_previa);

    $saldo_amanecer = 0;
    if ($res_toma && $res_toma->num_rows > 0) {
        $toma = $res_toma->fetch_assoc();
        $saldo_amanecer = floatval($toma['litros']);
        $fecha_toma_previa = $toma['fecha_hora'];

        $sql_consumo_previo = "SELECT SUM(COALESCE(litros_diesel, 0)) as consumido 
                               FROM grupoam6_diesel_jiuviz.registros_entrada 
                               WHERE timestamp > '$fecha_toma_previa' AND timestamp < '$inicio_dia' AND validado = 1";
        $res_cons_prev = $conn->query($sql_consumo_previo);
        $consumo_previo = floatval($res_cons_prev->fetch_assoc()['consumido'] ?? 0);

        $sql_entrada_previa = "SELECT SUM(COALESCE(litros_reales, 0)) as ingresado 
                               FROM entradas_almacen 
                               WHERE CONCAT(fecha, ' ', hora) > '$fecha_toma_previa' AND CONCAT(fecha, ' ', hora) < '$inicio_dia'";
        $res_ent_prev = $conn->query($sql_entrada_previa);
        $entrada_previa = floatval($res_ent_prev->fetch_assoc()['ingresado'] ?? 0);

        // Saldo real contable
        $saldo_amanecer = $saldo_amanecer - $consumo_previo + $entrada_previa;
    }

    $saldo_actual = $saldo_amanecer;
    $transacciones = [];

    $sql_tomas_hoy = "SELECT 'TOMA' as tipo, CAST(no_muestra AS CHAR) as id_ref, CAST(CONCAT(fecha, ' ', hora) AS CHAR) as timestamp, litros as cantidad, CAST('Gerente' AS CHAR) as unidad 
                      FROM registro_tomas WHERE fecha = '$fecha'";

    $sql_consumos_hoy = "SELECT 'CONSUMO' as tipo, CAST(r.id AS CHAR) as id_ref, CAST(r.timestamp AS CHAR) as timestamp, r.litros_diesel as cantidad, CAST(IFNULL(u.unit_number, 'N/D') AS CHAR) as unidad 
                         FROM grupoam6_diesel_jiuviz.registros_entrada r
                         LEFT JOIN grupoam6_diesel_jiuviz.units u ON r.unit_id = u.id
                         WHERE DATE(r.timestamp) = '$fecha' AND r.validado = 1";

    $sql_entradas_hoy = "SELECT 'ENTRADA' as tipo, CAST(id AS CHAR) as id_ref, CAST(CONCAT(fecha, ' ', hora) AS CHAR) as timestamp, litros_reales as cantidad, CAST(proveedor AS CHAR) as unidad 
                         FROM entradas_almacen WHERE fecha = '$fecha'";

    $sql_eventos = "$sql_tomas_hoy UNION ALL $sql_consumos_hoy UNION ALL $sql_entradas_hoy ORDER BY timestamp ASC";
    $res_eventos = $conn->query($sql_eventos);

    while ($evento = $res_eventos->fetch_assoc()) {
        $cantidad = floatval($evento['cantidad']);
        $hora = date('h:i A', strtotime($evento['timestamp']));

        if ($evento['tipo'] === 'TOMA') {
            $saldo_actual = $cantidad;
            $transacciones[] = [
                'id' => $evento['id_ref'],
                'tipo' => 'TOMA FISICA',
                'unidad' => 'GERENTE (MEDICIÓN)',
                'nivel_antes' => '-',
                'cantidad' => $cantidad,
                'nivel_despues' => $saldo_actual,
                'hora' => $hora
            ];
        } else if ($evento['tipo'] === 'ENTRADA' && $cantidad > 0) {
            $nivel_antes = $saldo_actual;
            $saldo_actual += $cantidad; // SUMA LIBRE

            $transacciones[] = [
                'id' => $evento['id_ref'],
                'tipo' => 'ENTRADA ALMACEN',
                'unidad' => $evento['unidad'],
                'nivel_antes' => $nivel_antes,
                'cantidad' => $cantidad,
                'nivel_despues' => $saldo_actual,
                'hora' => $hora
            ];
        } else if ($evento['tipo'] === 'CONSUMO' && $cantidad > 0) {
            $nivel_antes = $saldo_actual;
            $saldo_actual -= $cantidad; // RESTA LIBRE

            $transacciones[] = [
                'id' => $evento['id_ref'],
                'tipo' => 'DESPACHO',
                'unidad' => $evento['unidad'],
                'nivel_antes' => $nivel_antes,
                'cantidad' => $cantidad,
                'nivel_despues' => $saldo_actual,
                'hora' => $hora
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'saldo_amanecer' => $saldo_amanecer,
        'saldo_cierre' => $saldo_actual,
        'data' => $transacciones
    ]);
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>