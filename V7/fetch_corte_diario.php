<?php
// reportes_jiuviz/fetch_corte_diario.php
header('Content-Type: application/json; charset=utf-8');
require 'db_config.php';
ini_set('display_errors', 0);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error)
        throw new Exception("Error conexión BD: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");

    $dias_a_mostrar = 10;
    $historial = [];

    for ($i = 0; $i < $dias_a_mostrar; $i++) {
        $fecha_objetivo = date('Y-m-d', strtotime("-$i days"));
        $corte_actual = $fecha_objetivo . ' 21:00:00';
        $corte_anterior = date('Y-m-d', strtotime("-$i days -1 day")) . ' 21:00:00';

        // 1. Obtener la última toma física (Nivel Base)
        $sql_toma = "SELECT litros, CONCAT(fecha, ' ', hora) as fecha_hora 
                     FROM registro_tomas 
                     WHERE CONCAT(fecha, ' ', hora) <= '$corte_actual' 
                     ORDER BY fecha DESC, hora DESC LIMIT 1";
        $res_toma = $conn->query($sql_toma);

        if ($res_toma && $res_toma->num_rows > 0) {
            $toma = $res_toma->fetch_assoc();
            $litros_fisicos_base = floatval($toma['litros']);
            $fecha_hora_toma = $toma['fecha_hora'];

            // 2. Sumar consumo acumulado desde la toma hasta el corte (SOLO VALIDADOS)
            $sql_consumo_acumulado = "SELECT SUM(COALESCE(litros_diesel, 0) + COALESCE(litros_auto, 0)) as consumido_total 
                                      FROM grupoam6_diesel_jiuviz.registros_entrada 
                                      WHERE timestamp > '$fecha_hora_toma' AND timestamp <= '$corte_actual' AND validado = 1";
            $res_consumo_acumulado = $conn->query($sql_consumo_acumulado);
            $consumo_acumulado = floatval($res_consumo_acumulado->fetch_assoc()['consumido_total'] ?? 0);

            // 3. Sumar ENTRADAS (PIPAS) desde la toma hasta el corte
            $sql_entrada_acumulada = "SELECT SUM(COALESCE(litros_reales, 0)) as ingresado 
                                      FROM entradas_almacen 
                                      WHERE CONCAT(fecha, ' ', hora) > '$fecha_hora_toma' AND CONCAT(fecha, ' ', hora) <= '$corte_actual'";
            $res_entrada_acumulada = $conn->query($sql_entrada_acumulada);
            $entrada_acumulada = floatval($res_entrada_acumulada->fetch_assoc()['ingresado'] ?? 0);

            // Cálculo real contable (Sin límites)
            $litros_finales = $litros_fisicos_base + $entrada_acumulada - $consumo_acumulado;

            // 4. Obtener consumo del día actual (SOLO VALIDADOS)
            $sql_consumo_dia = "SELECT SUM(COALESCE(litros_diesel, 0) + COALESCE(litros_auto, 0)) as consumido_dia 
                                FROM grupoam6_diesel_jiuviz.registros_entrada 
                                WHERE timestamp > '$corte_anterior' AND timestamp <= '$corte_actual' AND validado = 1";
            $res_consumo_dia = $conn->query($sql_consumo_dia);
            $consumo_dia = floatval($res_consumo_dia->fetch_assoc()['consumido_dia'] ?? 0);

            // 5. Obtener entradas del día actual
            $sql_entrada_dia = "SELECT SUM(COALESCE(litros_reales, 0)) as ingresado_dia 
                                FROM entradas_almacen 
                                WHERE CONCAT(fecha, ' ', hora) > '$corte_anterior' AND CONCAT(fecha, ' ', hora) <= '$corte_actual'";
            $res_entrada_dia = $conn->query($sql_entrada_dia);
            $entrada_dia = floatval($res_entrada_dia->fetch_assoc()['ingresado_dia'] ?? 0);

            // Calcular inicial real (Sin límites)
            $litros_iniciales = $litros_finales + $consumo_dia - $entrada_dia;

            $historial[] = [
                'fecha' => $fecha_objetivo,
                'hora' => '21:00',
                'litros_iniciales' => $litros_iniciales,
                'consumido_dia' => $consumo_dia,
                'litros_finales' => $litros_finales
            ];
        }
    }

    echo json_encode(['status' => 'success', 'data' => $historial]);
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>