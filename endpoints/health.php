<?php

// Incluimos el fichero de configuracion database.inc
require_once __DIR__ . '/../config/database.inc';

header('Content-Type: application/json');

// Conectamos con la base de datos
$db = get_db_connection();

// Error si no se establece la conexion
if (!$db) {
    echo json_encode([
        "status" => "error",
        "database" => "unreachable"
    ]);
    exit;
}

// Ejecutamos una consulta a la base de datos
try {
    $stmt = $db->query("SELECT 1");
    $stmt->fetch();

    echo json_encode([
        "status" => "ok",
        "database" => "reachable"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "database" => "unreachable"
    ]);
}
