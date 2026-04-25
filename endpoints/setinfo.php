<?php

// Incluimos el ficheros de configuracion database.inc
require_once __DIR__ . '/../config/database.inc';


// FUNCIONES DE BASE DE DATOS

// Busca el set en la base de datos
function db_get_set($db, $set_num) {
    $stmt = $db->prepare("SELECT * FROM legoset WHERE id = ?");
    $stmt->execute([$set_num]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Busca las minifiguras que pertenecen a un set
function db_get_minifigs($db, $set_num) {
    $stmt = $db->prepare("
        SELECT m.id AS fig_num, m.name, ml.quantity, m.imageurl AS fig_img_url
        FROM minifig m
        JOIN minifiglegoset ml ON m.id = ml.idminifig
        WHERE ml.idlegoset = ?
    ");
    $stmt->execute([$set_num]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Inserta un set en la base de datos
function db_insert_set($db, $set) {
    $stmt = $db->prepare("
        INSERT INTO legoset (id, name, year, theme, imageurl)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $set["set_num"],
        $set["name"],
        $set["year"],
        $set["theme_name"],
        $set["set_img_url"] ?? null
    ]);
}

// Inserta las minifiguras que pertecen a un set
// Hacemos "INSERT OR IGNORE" porque la misma minifigura puede estar en mas de un set
// y puede que ya se encuentre en la base de datos
function db_insert_minifig($db, $mf) {
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO minifig (id, name, imageurl)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $mf["fig_num"],
        $mf["name"],
        $mf["fig_img_url"] ?? null
    ]);
}

// Inserta los datos con la relacion entre las minifiguras y el set
function db_insert_minifig_relation($db, $set_num, $mf) {
    $stmt = $db->prepare("
        INSERT INTO minifiglegoset (idlegoset, idminifig, quantity)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $set_num,
        $mf["fig_num"],
        $mf["quantity"]
    ]);
}


// FUNCIONES API REBRICKABLE

// Funcion generica para realizar llamadas a la API
function rebrickable_api_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: key " . REBRICKABLE_API_KEY
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) return null;

    $data = json_decode($response, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}


// Llamada al endpoint /api/v3/lego/sets/{set_num}/
// Devuelve los detalles de un set
function get_set_info($set_num) {
    return rebrickable_api_get("https://rebrickable.com/api/v3/lego/sets/$set_num/");
}

// Llamada al endpoint /api/v3/lego/themes/{theme_id}/
// Devuelve el tema al que pertenece el set
function get_theme_name($theme_id) {
    $data = rebrickable_api_get("https://rebrickable.com/api/v3/lego/themes/$theme_id/");
    return $data["name"] ?? null;
}

// Llamada al endpoint /api/v3/lego/sets/{set_num}/minifigs/
// Devuelve un listado con las minifiguras del set
function get_minifigs($set_num) {
    $data = rebrickable_api_get("https://rebrickable.com/api/v3/lego/sets/$set_num/minifigs/");
    $result = [];

    if ($data && isset($data["results"])) {
        foreach ($data["results"] as $mf) {
            $result[] = [
                "fig_num"      => $mf["set_num"] ?? null,
                "name"         => $mf["set_name"] ?? null,
                "quantity"     => $mf["quantity"] ?? 1,
                "fig_img_url"  => $mf["set_img_url"] ?? null
            ];
        }
    }
    return $result;
}


// VALIDACIONES INICIALES

header("Content-Type: application/json");

// Comprobamos que la API Key esta definida
if (!defined("REBRICKABLE_API_KEY")) {
    echo json_encode(["error" => "API Key no definida"]);
    exit;
}

// Comprobamos que llega el parametro set
if (!isset($_GET['set'])) {
    echo json_encode(["error" => "Falta el parametro 'set'"]);
    exit;
}

// Seguridad: eliminamos espacios y caracteres invisibles
$set_param = $_GET['set'];
$set_param = trim($set_param);

// Seguridad: comprobamos el fomato de set_param
// Permitimos numeros y numeros con un guion
if (!preg_match('/^[0-9]+(-[0-9]+)?$/', $set_param)) {
    echo json_encode(["error" => "Formato invalido. Solo se permiten numeros o numeros con guion. (ej:75347-1)"]);
    exit;
}

// La API de Rebrickable requiere que los numeros de set contengan
// la versión (indicada con -1, -2, etc.). Si set_param solo tiene
// el numero, añadimos "-1""
if (preg_match('/^[0-9]+$/', $set_param)) {
    $set_param .= "-1";
}

// Codificación segura para URL
$set_num = urlencode($set_param);


// INTENTAR OBTENER EL SET DESDE LA BASE DE DATOS

$db = get_db_connection();
$set_db = db_get_set($db, $set_num);

// El set existe en la base de datos
if ($set_db) {
    // Recuperamos las  minifigs
    $minifigs_db = db_get_minifigs($db, $set_num);

    // Mostramos el JSON con los resultados
    $response = [
        "origin"      => "database",
        "set_num"     => $set_db["id"],
        "name"        => $set_db["name"],
        "year"        => $set_db["year"],
        "theme_name"  => $set_db["theme"],
        "set_img_url" => $set_db["imageurl"],
        "minifigs"    => $minifigs_db
    ];
    echo json_encode($response);
    
    // Finalizamos la ejecucion
    exit;
}


// EL SET NO ESTA EN LA BASE DE DATOS: CONSULTAMOS A LA API DE REBRICKABLE

// Primera llamada a la API para obtener datos del set
$set_data = get_set_info($set_num);

// Si no existe o hay un error, finalizamos la ejecucion
if (!$set_data || isset($set_data["detail"])) {
    echo json_encode(["error" => "Set no encontrado o error en la API"]);
    exit;
}

// Segunda llamada a la API: buscamos el tema del set
$theme_name = get_theme_name($set_data["theme_id"]);

// Tercera llamada a la API: buscamos las minifiguras del set
$minifigs = get_minifigs($set_num);


// GUARDAMOS LA INFORMACION EN LA BASE DE DATOS

// Insertamos los datos del set
db_insert_set($db, [
    "set_num"     => $set_data["set_num"],
    "name"        => $set_data["name"],
    "year"        => $set_data["year"],
    "theme_name"  => $theme_name,
    "set_img_url" => $set_data["set_img_url"]
]);

// Insertamos las minifiguras 
foreach ($minifigs as $mf) {
    db_insert_minifig($db, $mf);
    db_insert_minifig_relation($db, $set_num, $mf);
}

// Mostramos el JSON con los resultados
$response = [
    "origin"      => "rebrickable",
    "set_num"     => $set_data["set_num"],
    "name"        => $set_data["name"],
    "year"        => $set_data["year"],
    "theme_name"  => $theme_name,
    "set_img_url" => $set_data["set_img_url"],
    "minifigs"    => $minifigs
];
echo json_encode($response);
