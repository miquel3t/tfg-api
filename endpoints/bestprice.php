<?php
header("Content-Type: application/json");

// VALIDACIONES INICIALES
// Comprobamos que llega el parametro set
if (!isset($_GET['set'])) {
    echo json_encode(["error" => "Falta el parametro 'set'"]);
    exit;
}

// Seguridad: eliminamos espacios y caracteres invisibles
$set_raw = trim($_GET['set']);

// Seguridad: comprobamos que contenga solo numeros
if (!preg_match('/^[0-9]+$/', $set_raw)) {
    echo json_encode(["error" => "Formato invalido. Solo se permiten numeros"]);
    exit;
}

// Convertimos a numero entero
$id = intval($set_raw);


// BUSCAMOS EL MEJOR PRECIO

// Array con los endpoints de todas las tiendas
$endpoints = [
    "abacus" => "http://localhost/api/abacus/$id",
    "alcampo" => "http://localhost/api/alcampo/$id",
    "brickoutique" => "http://localhost/api/brickoutique/$id"
];

// Array para almacenar las tiendas con stock
$disponibles = [];   
// Array para almacenar los tiendas sin stock
$agotados = [];

// Recorremos todas las tiendas
foreach ($endpoints as $tienda => $url) {

    // Recogemos el JSON que nos devuelve cada endpoint
    $json = @file_get_contents($url);

    // Si no obtenemos un JSON, saltamos a la siguiente tienda
    if (!$json) continue;

    // Si no hay datos, o contienen un error, saltamos a la siguiente
    $data = json_decode($json, true);
    if (!$data || isset($data["error"])) continue;

    // Si no hay el precio, saltamos a la siguiente tienda
    if (!isset($data["price"])) continue;

    // Capturamos el precio y el estado del set
    $precio = floatval(str_replace(",", ".", $data["price"]));
    $status = $data["status"] ?? "Agotado"; // siempre será Disponible o Agotado

    // Guardamos todo en un array
    $entrada = [
        "idlegoset" => $id,
        "site"   => $data["site"] ?? ucfirst($tienda),
        "price"  => $precio,
        "status" => $status,
        "url"    => $data["url"] ?? ""
    ];

    // En funcion del estado del set, lo almacenamos en el array 
    // disponibles o en agotados
    if ($status === "Disponible") {
        $disponibles[] = $entrada;
    } else {
        $agotados[] = $entrada;
    }
}

// Opcion 1: No hay disponibilidad en ninguna tienda
if (empty($disponibles) && empty($agotados)) {
    echo json_encode([
        "warning" => "No se ha encontrado el set en ninguna tienda"
    ]);
    exit;
}

// Opcion 2: Tenemos disponibilidad en almenos una tienda
if (!empty($disponibles)) {
    // Ordenamos el array en funcion del precio
    // El mas barato sera el primer elemento del array
    usort($disponibles, fn($a, $b) => $a["price"] <=> $b["price"]);

    echo json_encode($disponibles[0]);
    exit;
}

// Opcion 3: No hay disponibilidad en ninguna tienda, pero el
// set existe en almenos una de ellas
// Ordenamos el array en funcion del precio
// El mas barato sera el primer elemento del array
usort($agotados, fn($a, $b) => $a["price"] <=> $b["price"]);

// Añadimos un warning a la respuesta
$agotados[0]["warning"] = "No hay disponibilidad en ninguna tienda. Mostrando el precio mas economico.";

echo json_encode($agotados[0]);
?>
