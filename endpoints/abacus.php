<?php

header("Content-Type: application/json");

// Incluimos el fichero de configuracion database.inc
require_once __DIR__ . '/../config/database.inc';

$site = "Abacus";
$pricedate = date("Ymd");


// VALIDACIONES INICIALES
// Comprobamos que llega el parametro set
if (!isset($_GET['set'])) {
    echo json_encode(["error" => "Falta el parametro 'set'"]);
    exit;
}

// Seguridad: eliminamos espacios y caracteres invisibles
$id = trim($_GET['set']);

// Seguridad: comprobamos el formato de id
if (!preg_match('/^[0-9]+$/', $_GET['set'])) {
    echo json_encode([
        "error" => "Formato invalido. Solo se permiten numeros"]);
    exit;
}

// INTENTAMOS OBTENER EL PRECIO DESDE LA BASE DE DATOS

$db = get_db_connection();
$set_legoprice = db_get_legoprice($db, $id, $site, $pricedate);

// Tenemos el precio en la base de datos
if ($set_legoprice) {
    // Mostramos el JSON con los resultados
    echo json_encode($set_legoprice);
    exit;
}

// EL PRECIO NO ESTA EN LA BASE DE DATOS: BUSCAMOS EN ABACUS

// Construimos la peticioin URL con cUrl
// Utilizamos la API interna de Abacus demandware.store
$idEncoded = urlencode($id);
$apiUrl = "https://www.abacus.coop/on/demandware.store/Sites-abacus_es-Site/ca_ES/Search-UpdateGrid?q=" . $idEncoded;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_setopt($ch, CURLOPT_USERAGENT,
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"
);

$html = curl_exec($ch);
curl_close($ch);

// Si no hay respuesta de Abacus, mostramos un error
if (!$html) {
    die(json_encode([
        "idlegoset" => $id,
        "error" => "No se han podido obtener datos de Abacus",
        "site" => $site,
        "pricedate" => $pricedate
    ]));
}

// Parseamos el HTML recibido
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Buscamos los DIV con el valor product-tile en el atributo class
$products = $xpath->query("//div[contains(@class,'product-tile')]");

// Si no hay ninguno, no hemos encontrado el Lego 
if ($products->length === 0) {
    echo json_encode([
        "idlegoset" => $id,
        "error" => "No se ha encontrado el set indicado",
        "site" => $site,
        "pricedate" => $pricedate
    ]);
    exit;
}

// Revisamos los sets encontrados
foreach ($products as $product) {

    // Buscamos un enlace A con el valor link en el atributo class
    $nameNode = $xpath->query(".//a[contains(@class,'link')]", $product);
    
    // Si no lo encuentra, saltamos al siguiente
    if ($nameNode->length === 0) continue;

    // Extraemos el nombre del set
    $nombre = trim($nameNode->item(0)->textContent);

    // Si el id del set no esta contenido en $nombre, saltamos al siguiente
    if (!preg_match("/\b" . preg_quote($id, '/') . "\b/", $nombre)) {
        continue;
    }

    // Buscamos el precio del set dentro de un span con class="value"
    // que esta dentro de otro span class="sales" para encontrar el precio
    // Si no lo encontramos, saltamos al siguiente
    $priceNode = $xpath->query(".//span[contains(@class,'sales')]//span[contains(@class,'value')]", $product);
    if ($priceNode->length === 0) continue;

    $price = trim($priceNode->item(0)->textContent);

    // Formateamos el precio para eliminar el simbolo de euro, espacios y poner . como semparador de decimales
    $price = str_replace(['€', ' ', "\xc2\xa0"], '', $price); // afegim NBSP
    $price = str_replace(',', '.', $price);

    // Buscamos la palabra 'esgotat' para comprobar si hay disponibilidad del set
    $statusNode = $xpath->query(".//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'esgotat')]", $product);
    $status = ($statusNode->length > 0) ? "Agotado temporalmente" : "Disponible";

    // Buscamos la URL del set
    $href = $nameNode->item(0)->getAttribute("href");
    $productUrl = "https://www.abacus.coop" . $href;

    // Si hemos llegado hasta aqui, insertamos la informacion en la base de datos
    db_insert_legoprice($db, $id, $price, $site, $productUrl, $pricedate, $status);

    // Y mostramos el JSON con los resultados
    echo json_encode([
        "idlegoset" => $id,
        "price" => $price,
        "site" => $site,
        "url" => $productUrl,
        "pricedate" => $pricedate,
        "status" => $status,
        "origin" => "Abacus"
    ]);
    exit;
}

// Si no ha habido coincidencia mostramos un error
echo json_encode([
    "idlegoset" => $id,
    "error" => "No se ha encontrado coincidencia",
    "site" => $site,
    "pricedate" => $pricedate
]);
?>
